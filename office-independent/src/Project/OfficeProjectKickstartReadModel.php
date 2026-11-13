<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use PDO;
use DomainException;

/**
 * Read-only dossier retrieval for an already authorized project and scope.
 * Controller must verify project ACL before invoking.
 */
final class OfficeProjectKickstartReadModel
{
    public function __construct(private readonly PDO $db) {}

    public function forAuthorizedScope(int $projectId, string $scopeType, int $scopeId): ?array
    {
        if ($projectId < 1 || $scopeId < 0 || !in_array($scopeType, ['project','building','zone','phase'], true)) {
            throw new DomainException('Invalid project scope');
        }
        $q = $this->db->prepare('SELECT id, project_id, scope_type, scope_id, revision, status, items_json, critical_open_points, created_at, updated_at FROM office_project_start_dossiers WHERE project_id = ? AND scope_type = ? AND scope_id = ? LIMIT 1');
        $q->execute([$projectId,$scopeType,$scopeId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $items = json_decode((string)$row['items_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($items)) throw new DomainException('Invalid dossier checklist');
        $a = $this->db->prepare('SELECT dossier_revision, actor_user_id, decision, note, created_at FROM office_project_start_approvals WHERE dossier_id = ? ORDER BY id ASC');
        $a->execute([(int)$row['id']]);
        $approvals = [];
        while ($approval = $a->fetch(PDO::FETCH_ASSOC)) {
            $approvals[] = [
                'revision'=>(int)$approval['dossier_revision'],
                'actor_user_id'=>(int)$approval['actor_user_id'],
                'decision'=>(string)$approval['decision'],
                'note'=>$approval['note'],
                'created_at'=>(string)$approval['created_at'],
            ];
        }
        $questionsQuery = $this->db->prepare('SELECT id, created_revision, question, answer, decision_text, owner_user_id, due_date, is_critical, created_at FROM office_project_start_questions WHERE dossier_id = ? ORDER BY id ASC');
        $questionsQuery->execute([(int)$row['id']]);
        $questions = [];
        while ($question = $questionsQuery->fetch(PDO::FETCH_ASSOC)) {
            $questions[] = [
                'id'=>(int)$question['id'],
                'created_revision'=>(int)$question['created_revision'],
                'question'=>(string)$question['question'],
                'answer'=>(string)$question['answer'],
                'decision'=>(string)$question['decision_text'],
                'owner_user_id'=>$question['owner_user_id'] === null ? null : (int)$question['owner_user_id'],
                'owner_label'=>$question['owner_user_id'] === null ? 'Niet toegewezen' : 'Gebruiker #'.(int)$question['owner_user_id'],
                'due_date'=>$question['due_date'],
                'is_critical'=>(bool)$question['is_critical'],
                'created_at'=>(string)$question['created_at'],
            ];
        }
        return [
            'id'=>(int)$row['id'],
            'project_id'=>(int)$row['project_id'],
            'scope_type'=>(string)$row['scope_type'],
            'scope_id'=>(int)$row['scope_id'],
            'revision'=>(int)$row['revision'],
            'status'=>(string)$row['status'],
            'items'=>$items,
            'critical_open_points'=>(int)$row['critical_open_points'],
            'created_at'=>(string)$row['created_at'],
            'updated_at'=>(string)$row['updated_at'],
            'approvals'=>$approvals,
            'questions'=>$questions,
        ];
    }
}
