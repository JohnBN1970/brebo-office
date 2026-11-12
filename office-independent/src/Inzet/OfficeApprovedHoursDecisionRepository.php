<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/** Read a persisted decision; caller must use this evidence, not request data. */
final class OfficeApprovedHoursDecisionRepository
{
    public function __construct(private PDO $db) {}

    /** @return array{session:array<string,mixed>,decision:array<string,mixed>} */
    public function verified(string $decisionReference, string $authenticatedReviewer): array
    {
        if ($decisionReference === '' || strlen($decisionReference) > 128
            || $authenticatedReviewer === '' || strlen($authenticatedReviewer) > 128) {
            throw new \InvalidArgumentException('Invalid approval lookup');
        }
        $stmt = $this->db->prepare("SELECT d.decision_reference,d.in_event_id,d.out_event_id,
                d.project_id,d.person_id,d.work_date,d.status,d.reviewed_seconds,
                d.reviewer_id,d.decided_at
            FROM office_onsite_hours_decisions d
            WHERE d.decision_reference = :reference AND d.reviewer_id = :reviewer
              AND d.status = 'approved'
              AND EXISTS (
                SELECT 1 FROM office_operator_accounts a
                JOIN office_project_memberships m ON m.operator_id = a.id
                WHERE a.id = :reviewer_access AND a.active = 1
                  AND m.project_id = d.project_id AND m.permission = 'manage'
              )
            LIMIT 1");
        $stmt->execute([
            ':reference'=>$decisionReference, ':reviewer'=>$authenticatedReviewer,
            ':reviewer_access'=>$authenticatedReviewer,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \DomainException('No authorized persisted approval found');
        }
        return [
            'session'=>[
                'project_id'=>(int)$row['project_id'],
                'person_id'=>(int)$row['person_id'],
                'work_date'=>(string)$row['work_date'],
                'in_event_id'=>(string)$row['in_event_id'],
                'out_event_id'=>(string)$row['out_event_id'],
            ],
            'decision'=>[
                'status'=>(string)$row['status'],
                'persisted'=>true,
                'decision_id'=>(string)$row['decision_reference'],
                'approved_at'=>(string)$row['decided_at'],
                'reviewed_seconds'=>(int)$row['reviewed_seconds'],
                'reviewer_id'=>(string)$row['reviewer_id'],
            ],
        ];
    }
}
