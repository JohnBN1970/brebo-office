<?php
declare(strict_types=1);
namespace Brebo\Office\Approval;
use PDO;
/** Site-wide administration authority; independent of project membership and CRM edit. */
final class OfficeTestSiteAdministratorAccess {
    public function __construct(private readonly PDO $db) {}
    public function isAuthorized(string $operatorId, bool $isTest): bool {
        if (!$isTest || $operatorId === '' || strlen($operatorId) > 128) return false;
        try {
            $q = $this->db->prepare(
                'SELECT 1 FROM office_test_site_administrators admins ' .
                'INNER JOIN office_operator_accounts accounts ON accounts.id=admins.operator_id ' .
                'WHERE admins.operator_id=:operator_id AND accounts.active=1 LIMIT 1'
            );
            $q->execute([':operator_id' => $operatorId]);
            return $q->fetchColumn() !== false;
        } catch (\Throwable $e) {
            // A missing schema or a DB failure must never grant access.
            return false;
        }
    }
}
