<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/** Module-specific visibility filter, independent of action permission. */
final class OfficeOfferRoleScope
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function forOperator(int|string $operatorId, string $module): string
    {
        if ((is_int($operatorId) ? $operatorId < 1 : trim($operatorId) === '') || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $module)) {
            return 'none';
        }
        $query = $this->db->prepare(
            "SELECT s.scope_key FROM office_role_scopes s
             JOIN office_roles r ON r.id = s.role_id AND r.active = 1
             JOIN office_user_roles ur ON ur.role_id = r.id
             JOIN office_operator_accounts a ON a.id = ur.operator_id AND a.active = 1
             WHERE ur.operator_id = :operator_id AND s.module = :module"
        );
        $query->execute([':operator_id' => $operatorId, ':module' => $module]);
        $scopes = $query->fetchAll(PDO::FETCH_COLUMN);
        if (!$scopes) {
            return 'none';
        }
        foreach (['all', 'assigned', 'own'] as $scope) {
            if (in_array($scope, $scopes, true)) {
                return $scope;
            }
        }
        return 'none';
    }
}
