<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use Brebo\Office\Project\OfficeNativeProjectAccess;
use PDO;
use Throwable;

/**
 * Independent Office-test Calc authorization.
 *
 * Project manage permission is necessary, never sufficient. A separate
 * calculation-level grant must be verified before issuing a launch token.
 * Missing tables/columns and database errors fail closed.
 */
final class OfficeCalcLaunchAccess
{
    public function __construct(private readonly PDO $db) {}

    public function canLaunch(int $operatorId, int $projectId, int $calculationId): bool
    {
        if ($operatorId <= 0 || $projectId <= 0 || $calculationId <= 0) {
            return false;
        }
        try {
            require_once dirname(__DIR__) . '/Project/OfficeNativeProjectAccess.php';
            if (!(new OfficeNativeProjectAccess($this->db))->canManage($operatorId, $projectId)) {
                return false;
            }
            // Require an existing, explicit per-calculation access row.
            // The expected schema must be confirmed by the staging preflight.
            $statement = $this->db->prepare(
                'SELECT 1 FROM office_calc_calculations c
                 INNER JOIN office_calc_project_access ca
                   ON ca.calculation_id = c.id AND ca.project_id = c.project_id
                 WHERE c.id = :calculation_id AND c.project_id = :project_id
                   AND ca.operator_id = :operator_id
                   AND ca.permission = :permission LIMIT 1'
            );
            $statement->execute([
                ':calculation_id' => $calculationId,
                ':project_id' => $projectId,
                ':operator_id' => $operatorId,
                ':permission' => 'manage',
            ]);
            return $statement->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
