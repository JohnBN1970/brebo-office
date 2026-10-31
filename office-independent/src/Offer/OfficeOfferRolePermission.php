<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/** Optional role-based grants; existing direct grants remain supported separately. */
final class OfficeOfferRolePermission
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function allows(int $operatorId, string $permission): bool
    {
        if ($operatorId < 1 || !in_array($permission, [
            'read', 'create', 'update', 'issue', 'record_acceptance', 'link_change', 'manage',
        ], true)) {
            return false;
        }
        $query = $this->db->prepare(
            "SELECT 1 FROM office_user_roles ur
             JOIN office_roles r ON r.id = ur.role_id AND r.active = 1
             JOIN office_role_permissions rp ON rp.role_id = r.id
             JOIN office_operator_accounts a ON a.id = ur.operator_id AND a.active = 1
             WHERE ur.operator_id = :operator_id AND rp.module = 'offers'
               AND rp.permission = :permission LIMIT 1"
        );
        $query->execute([':operator_id' => $operatorId, ':permission' => $permission]);
        return $query->fetchColumn() !== false;
    }
}
