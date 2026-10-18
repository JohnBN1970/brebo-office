<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Read-only project-team eligibility boundary. Uses the same Office ACL and
 * team join as the native Inzet shift command in PR #1407.
 */
final class OfficeInzetVerifiedAssignmentEligibility
{
    public function __construct(private PDO $db) {}

    /** @param array{project_id:int,person_id:int} $assignment */
    public function assertManageable(string $operatorId, array $assignment): void
    {
        $project = $assignment['project_id'] ?? null;
        $person = $assignment['person_id'] ?? null;
        if ($operatorId === '' || strlen($operatorId) > 128
            || !is_int($project) || $project < 1 || !is_int($person) || $person < 1) {
            throw new RuntimeException('Invalid operator or assignment');
        }
        $query = $this->db->prepare(
            "SELECT 1 FROM office_project_memberships m
             INNER JOIN office_operator_accounts a ON a.id = m.operator_id
             INNER JOIN office_inzet_project_team t ON t.project_id = m.project_id
             INNER JOIN office_inzet_people p ON p.id = t.person_id
             WHERE m.operator_id = ? AND m.project_id = ? AND m.permission = 'manage'
               AND a.active = 1 AND t.person_id = ? AND p.active = 1 LIMIT 1"
        );
        $query->execute([$operatorId, $project, $person]);
        if (!$query->fetchColumn()) {
            throw new RuntimeException('Project team assignment is not authorized');
        }
    }
}
