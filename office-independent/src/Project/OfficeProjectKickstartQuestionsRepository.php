<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use PDO;
use DomainException;

/**
 * Kickstart question/answer/decision register.
 * Caller must authenticate actor and authorize edit access to project/scope.
 * Every edit bumps dossier revision, revoking prior approval.
 */
final class OfficeProjectKickstartQuestionsRepository
{
    public function __construct(private readonly PDO $db) {}

    public function add(
        int $dossierId,
        int $expectedRevision,
        int $actorId,
        bool $canEdit,
        string $question,
        string $answer,
        string $decision,
        ?int $ownerUserId,
        ?string $dueDate,
        bool $critical
    ): array {
        if ($dossierId < 1 || $expectedRevision < 1 || $actorId < 1 || !$canEdit) {
            throw new DomainException('Unauthorized kickstart edit');
        }
        $question = trim($question);
        $answer = trim($answer);
        $decision = trim($decision);
        if ($question === '' || mb_strlen($question) > 2000 || mb_strlen($answer) > 10000 || mb_strlen($decision) > 10000) {
            throw new DomainException('Invalid question or answer');
        }
        if ($ownerUserId !== null && $ownerUserId < 1) throw new DomainException('Invalid owner');
        if ($dueDate !== null && !self::validDate($dueDate)) throw new DomainException('Invalid deadline');
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT revision, status, critical_open_points FROM office_project_start_dossiers WHERE id = ? FOR UPDATE');
            $q->execute([$dossierId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new DomainException('Dossier not found');
            if ((int)$row['revision'] !== $expectedRevision) throw new DomainException('Stale dossier revision');
            $next = $expectedRevision + 1;
            $insert = $this->db->prepare('INSERT INTO office_project_start_questions (dossier_id, created_revision, actor_user_id, question, answer, decision_text, owner_user_id, due_date, is_critical) VALUES (?,?,?,?,?,?,?,?,?)');
            $insert->execute([$dossierId,$next,$actorId,$question,$answer,$decision,$ownerUserId,$dueDate,(int)$critical]);
            $questionId = (int)$this->db->lastInsertId();
            // The manual critical counter tracks separate checklist issues.
            // Critical Q&A is evaluated directly from persisted decisions by the gate.
            $newCritical = (int)$row['critical_open_points'];
            $update = $this->db->prepare("UPDATE office_project_start_dossiers SET revision = ?, status = 'draft', critical_open_points = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND revision = ?");
            $update->execute([$next,$newCritical,$dossierId,$expectedRevision]);
            if ($update->rowCount() !== 1) throw new DomainException('Concurrent dossier update');
            $event = $this->db->prepare('INSERT INTO office_project_start_events (dossier_id,dossier_revision,actor_user_id,event_type,payload_json) VALUES (?,?,?,?,?)');
            $event->execute([$dossierId,$next,$actorId,'kickstart_question_added',json_encode(['question_id'=>$questionId,'previous_status'=>$row['status']],JSON_THROW_ON_ERROR)]);
            $this->db->commit();
            return ['id'=>$questionId,'revision'=>$next,'status'=>'draft'];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function listForAuthorizedDossier(int $dossierId): array
    {
        if ($dossierId < 1) throw new DomainException('Invalid dossier');
        $q = $this->db->prepare('SELECT id, created_revision, question, answer, decision_text, owner_user_id, due_date, is_critical, created_at FROM office_project_start_questions WHERE dossier_id = ? ORDER BY id ASC');
        $q->execute([$dossierId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function validDate(string $date): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }
}
