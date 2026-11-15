<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use PDO;
use DomainException;

/**
 * Persistent start-dossier gate. Caller authenticates the actor and checks
 * project/scope ACL before invoking. No direct HTTP access.
 */
final class OfficeProjectKickstartRepository
{
    public function __construct(private readonly PDO $db) {}

    /**
     * Initialize a single project start dossier. The controller must resolve
     * the project leader from a trusted Office assignment and verify ACL.
     * Never accept project or leader identifiers from browser input.
     */
    public function createForAuthorizedProject(int $projectId, int $projectLeaderUserId, int $actorId, bool $canManage): int
    {
        if ($projectId < 1 || $projectLeaderUserId < 1 || $actorId < 1 || !$canManage) {
            throw new DomainException('Unauthorized start dossier creation');
        }
        $items = array_fill_keys(OfficeProjectKickstartPolicy::REQUIRED_ITEMS, false);
        $json = json_encode($items, JSON_THROW_ON_ERROR);
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare("INSERT INTO office_project_start_dossiers (project_id,scope_type,scope_id,project_leader_user_id,revision,status,items_json,critical_open_points) VALUES (?,'project',0,?,1,'draft',?,0)");
            $q->execute([$projectId,$projectLeaderUserId,$json]);
            $id = (int)$this->db->lastInsertId();
            $event = $this->db->prepare('INSERT INTO office_project_start_events (dossier_id,dossier_revision,actor_user_id,event_type,payload_json) VALUES (?,?,?,?,?)');
            $event->execute([$id,1,$actorId,'dossier_created','{}']);
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /**
     * Save checklist as a new immutable revision marker. The authenticated
     * actor and project-edit ACL must be verified by the calling controller.
     * A stale editor cannot overwrite another person's changes.
     */
    public function saveChecklist(
        int $dossierId,
        int $expectedRevision,
        int $actorId,
        bool $canEdit,
        array $items,
        int $criticalOpenPoints
    ): array {
        if ($dossierId < 1 || $expectedRevision < 1 || $actorId < 1 || !$canEdit || $criticalOpenPoints < 0) {
            throw new DomainException('Invalid dossier edit');
        }
        $keys = OfficeProjectKickstartPolicy::REQUIRED_ITEMS;
        if (array_diff(array_keys($items), $keys) !== [] || count($items) !== count($keys)) {
            throw new DomainException('Unexpected or missing checklist fields');
        }
        foreach ($keys as $key) {
            if (!array_key_exists($key, $items) || !is_bool($items[$key])) {
                throw new DomainException('Invalid checklist value');
            }
        }
        $json = json_encode($items, JSON_THROW_ON_ERROR);
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT revision, status, items_json, critical_open_points FROM office_project_start_dossiers WHERE id = ? FOR UPDATE');
            $q->execute([$dossierId]);
            $current = $q->fetch(PDO::FETCH_ASSOC);
            if (!$current) throw new DomainException('Dossier not found');
            if ((int)$current['revision'] !== $expectedRevision) throw new DomainException('Dossier was modified by another user');
            $previous = (string)$current['status'];
            if (!in_array($previous, ['draft','changes_requested','awaiting_first_approval','awaiting_second_approval','released'], true)) {
                throw new DomainException('Invalid dossier status');
            }
            if (json_decode((string)$current['items_json'], true, 512, JSON_THROW_ON_ERROR) == $items && (int)$current['critical_open_points'] === $criticalOpenPoints) {
                $this->db->commit();
                return ['status'=>$previous,'revision'=>$expectedRevision,'changed'=>false];
            }
            $nextRevision = $expectedRevision + 1;
            $q = $this->db->prepare("UPDATE office_project_start_dossiers SET items_json = ?, critical_open_points = ?, revision = ?, status = 'draft', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND revision = ?");
            $q->execute([$json,$criticalOpenPoints,$nextRevision,$dossierId,$expectedRevision]);
            if ($q->rowCount() !== 1) throw new DomainException('Concurrent dossier update');
            $event = $this->db->prepare('INSERT INTO office_project_start_events (dossier_id, dossier_revision, actor_user_id, event_type, payload_json) VALUES (?,?,?,?,?)');
            $event->execute([$dossierId,$nextRevision,$actorId,'checklist_updated',json_encode([
                'previous_status'=>$previous,'previous_revision'=>$expectedRevision,
                'previous_items'=>json_decode((string)$current['items_json'], true, 512, JSON_THROW_ON_ERROR),
                'items'=>$items,'critical_open_points'=>$criticalOpenPoints,
            ], JSON_THROW_ON_ERROR)]);
            $revoke = $this->db->prepare("UPDATE office_projects p INNER JOIN office_project_start_dossiers d ON d.project_id=p.id SET p.status='preparation',p.updated_at=CURRENT_TIMESTAMP WHERE d.id=? AND p.status IN ('active','execution','in_progress')");
            $revoke->execute([$dossierId]);
            $this->db->commit();
            return ['status'=>'draft','revision'=>$nextRevision,'changed'=>true];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /**
     * Project leader submits a completed, current dossier revision.
     * Caller must resolve actor identity and edit rights from Office session/ACL.
     */
    public function submitForApproval(
        int $dossierId,
        int $expectedRevision,
        int $actorId,
        bool $authorized
    ): array {
        if ($dossierId < 1 || $expectedRevision < 1 || $actorId < 1 || !$authorized) {
            throw new DomainException('Unauthorized submission');
        }
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT * FROM office_project_start_dossiers WHERE id = ? FOR UPDATE');
            $q->execute([$dossierId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new DomainException('Dossier not found');
            if ((int)$row['revision'] !== $expectedRevision) throw new DomainException('Stale dossier revision');
            if ((int)$row['project_leader_user_id'] !== $actorId) throw new DomainException('Project leader required');
            if (!in_array((string)$row['status'], ['draft','changes_requested'], true)) {
                throw new DomainException('Invalid submission state');
            }
            $items = json_decode((string)$row['items_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($items) || OfficeProjectKickstartPolicy::missingItems($items) !== [] || (int)$row['critical_open_points'] !== 0 || $this->hasUnresolvedCriticalQuestions($dossierId)) {
                throw new DomainException('Incomplete start dossier');
            }
            $this->setStatus($dossierId, 'awaiting_first_approval', $expectedRevision);
            $event = $this->db->prepare('INSERT INTO office_project_start_events (dossier_id,dossier_revision,actor_user_id,event_type,payload_json) VALUES (?,?,?,?,?)');
            $event->execute([$dossierId,$expectedRevision,$actorId,'submitted_for_approval','{}']);
            $this->db->commit();
            return ['status'=>'awaiting_first_approval','revision'=>$expectedRevision];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function approve(int $dossierId, int $expectedRevision, int $actorId, bool $authorized, string $decision, ?string $note = null): array
    {
        if ($dossierId < 1 || $expectedRevision < 1 || $actorId < 1 || !$authorized || !in_array($decision, ['approve','reject'], true)) {
            throw new DomainException('Not authorized or invalid decision');
        }
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT * FROM office_project_start_dossiers WHERE id = ? FOR UPDATE');
            $q->execute([$dossierId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new DomainException('Dossier not found');
            $revision = (int)$row['revision'];
            if ($revision !== $expectedRevision) throw new DomainException('Stale dossier approval');
            $status = (string)$row['status'];
            $items = json_decode((string)$row['items_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($items)) throw new DomainException('Invalid checklist');
            if ($decision === 'reject') {
                if (trim($note ?? '') === '') throw new DomainException('Rejection reason required');
                if (!in_array($status, ['awaiting_first_approval','awaiting_second_approval'], true)) throw new DomainException('Invalid status');
                if ($status === 'awaiting_first_approval' && (int)$row['project_leader_user_id'] !== $actorId) {
                    throw new DomainException('Project leader required for first review');
                }
                if ($status === 'awaiting_second_approval') {
                    $first = $this->db->prepare("SELECT actor_user_id FROM office_project_start_approvals WHERE dossier_id = ? AND dossier_revision = ? AND decision = 'approved_first' ORDER BY id DESC LIMIT 1");
                    $first->execute([$dossierId,$revision]);
                    $firstActor = $first->fetchColumn();
                    if (!$firstActor || (int)$firstActor === $actorId) throw new DomainException('Independent second reviewer required');
                }
                $this->record($dossierId, $revision, $actorId, 'rejected', $note);
                $this->setStatus($dossierId, 'changes_requested', $revision);
                $this->db->commit();
                return ['status'=>'changes_requested','revision'=>$revision];
            }
            if (OfficeProjectKickstartPolicy::missingItems($items) !== [] || (int)$row['critical_open_points'] !== 0 || $this->hasUnresolvedCriticalQuestions($dossierId)) {
                throw new DomainException('Start dossier incomplete');
            }
            if ($status === 'awaiting_first_approval') {
                // Project leader identity is derived from the locked project assignment.
                if ((int)$row['project_leader_user_id'] !== $actorId) throw new DomainException('Project leader required');
                $this->record($dossierId, $revision, $actorId, 'approved_first', $note);
                $this->setStatus($dossierId, 'awaiting_second_approval', $revision);
                $next = 'awaiting_second_approval';
            } elseif ($status === 'awaiting_second_approval') {
                $first = $this->db->prepare("SELECT actor_user_id, dossier_revision FROM office_project_start_approvals WHERE dossier_id = ? AND dossier_revision = ? AND decision = 'approved_first' ORDER BY id DESC LIMIT 1");
                $first->execute([$dossierId,$revision]);
                $firstRow = $first->fetch(PDO::FETCH_ASSOC);
                $evaluation = OfficeProjectKickstartPolicy::evaluateSecondApproval([
                    'status'=>$status,'revision'=>$revision,'items'=>$items,
                    'critical_open_points'=>(int)$row['critical_open_points'],
                    'first_approver_role'=>'projectleider',
                ], $firstRow ? [
                    'decision'=>'approved',
                    'approver_id'=>(int)$firstRow['actor_user_id'],
                    'dossier_revision'=>(int)$firstRow['dossier_revision'],
                ] : null, $actorId, $authorized);
                if (!$evaluation['allowed']) throw new DomainException(implode(',', $evaluation['reasons']));
                $this->record($dossierId, $revision, $actorId, 'approved_second', $note);
                $this->setStatus($dossierId, 'released', $revision);
                $next = 'released';
            } else {
                throw new DomainException('Invalid status transition');
            }
            $this->db->commit();
            return ['status'=>$next,'revision'=>$revision];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /** Start execution under the same row lock used by dossier revisions. */
    public function startExecution(int $dossierId, int $expectedRevision, int $actorId, bool $authorized): array
    {
        if (!$authorized || $actorId < 1 || $dossierId < 1 || $expectedRevision < 1) throw new DomainException('Execution not authorized');
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT * FROM office_project_start_dossiers WHERE id=? FOR UPDATE');
            $q->execute([$dossierId]); $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['revision'] !== $expectedRevision || $row['scope_type'] !== 'project' || (int)$row['scope_id'] !== 0) throw new DomainException('Invalid execution scope or revision');
            $projectId = (int)$row['project_id'];
            $access = $this->db->prepare("SELECT 1 FROM office_project_memberships m JOIN office_operator_accounts a ON a.id=m.operator_id WHERE m.project_id=? AND m.operator_id=? AND m.permission='manage' AND a.active=1");
            $access->execute([$projectId,(string)$actorId]);
            if (!$access->fetchColumn()) throw new DomainException('Execution not authorized');
            $this->assertReleased($projectId,'project',0);
            $q = $this->db->prepare('SELECT status FROM office_projects WHERE id=? FOR UPDATE');
            $q->execute([$projectId]); $status=$q->fetchColumn();
            if (!in_array($status,['concept','preparation','active'],true)) throw new DomainException('Project cannot start from this status');
            $q = $this->db->prepare("UPDATE office_projects SET status='active',updated_at=CURRENT_TIMESTAMP WHERE id=?"); $q->execute([$projectId]);
            if ($status !== 'active') {
                $event=$this->db->prepare('INSERT INTO office_project_start_events (dossier_id,dossier_revision,actor_user_id,event_type,payload_json) VALUES (?,?,?,?,?)');
                $event->execute([$dossierId,$expectedRevision,$actorId,'execution_started','{}']);
            }
            $this->db->commit();
            return ['status'=>'active','revision'=>$expectedRevision];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function assertReleased(int $projectId, string $scopeType, int $scopeId): void
    {
        if ($projectId < 1 || $scopeId < 0 || !in_array($scopeType, ['project','building','zone','phase'], true)) {
            throw new DomainException('Invalid start scope');
        }
        // A status flag alone is insufficient: require both independent
        // approvals on the currently released dossier revision.
        $q = $this->db->prepare("SELECT id, revision, project_leader_user_id, items_json, critical_open_points FROM office_project_start_dossiers WHERE project_id = ? AND scope_type = ? AND scope_id = ? AND status = 'released' LIMIT 1");
        $q->execute([$projectId,$scopeType,$scopeId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['critical_open_points'] !== 0 || $this->hasUnresolvedCriticalQuestions((int)$row['id'])) {
            throw new DomainException('Execution blocked: start approval missing');
        }
        $items = json_decode((string)$row['items_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($items) || OfficeProjectKickstartPolicy::missingItems($items) !== []) {
            throw new DomainException('Execution blocked: incomplete start dossier');
        }
        $a = $this->db->prepare("SELECT actor_user_id, decision FROM office_project_start_approvals WHERE dossier_id = ? AND dossier_revision = ? AND decision IN ('approved_first','approved_second') ORDER BY id ASC");
        $a->execute([(int)$row['id'],(int)$row['revision']]);
        $first = null;
        $second = null;
        while ($approval = $a->fetch(PDO::FETCH_ASSOC)) {
            if ($approval['decision'] === 'approved_first') $first = (int)$approval['actor_user_id'];
            if ($approval['decision'] === 'approved_second') $second = (int)$approval['actor_user_id'];
        }
        if (!$first || !$second || $first === $second || $first !== (int)$row['project_leader_user_id']) {
            throw new DomainException('Execution blocked: independent approvals missing');
        }
    }

    /** Unresolved critical kickstart decisions cannot be bypassed by a manual counter. */
    private function hasUnresolvedCriticalQuestions(int $dossierId): bool
    {
        $q = $this->db->prepare("SELECT COUNT(*) FROM office_project_start_questions WHERE dossier_id = ? AND is_critical = 1 AND TRIM(decision_text) = ''");
        $q->execute([$dossierId]);
        return (int)$q->fetchColumn() > 0;
    }

    private function record(int $id, int $revision, int $actor, string $decision, ?string $note): void
    {
        $q=$this->db->prepare('INSERT INTO office_project_start_approvals (dossier_id,dossier_revision,actor_user_id,decision,note) VALUES (?,?,?,?,?)');
        $q->execute([$id,$revision,$actor,$decision,$note]);
    }

    private function setStatus(int $id, string $status, int $revision): void
    {
        $q=$this->db->prepare('UPDATE office_project_start_dossiers SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND revision = ?');
        $q->execute([$status,$id,$revision]);
        if ($q->rowCount() !== 1) throw new DomainException('Concurrent dossier update');
    }
}
