<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/** Explicit Office module grants; never infer grants from a project role. */
final class OfficeOfferModulePermission
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
            "SELECT 1 FROM office_module_permissions p
             INNER JOIN office_operator_accounts a ON a.id = p.operator_id
             WHERE p.operator_id = :operator_id AND p.module = 'offers'
               AND p.permission = :permission AND a.active = 1 LIMIT 1"
        );
        $query->execute([':operator_id' => $operatorId, ':permission' => $permission]);
        if ($query->fetchColumn() !== false) {
            return true;
        }
        // Role grants are optional until the central roles migration is installed.
        $schema = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name IN ('office_roles', 'office_user_roles', 'office_role_permissions')"
        );
        $schema->execute();
        if ((int) $schema->fetchColumn() !== 3) {
            return false;
        }
        // This class is used by standalone Office entrypoints without Composer autoloading.
        require_once __DIR__ . '/OfficeOfferRolePermission.php';
        if (!(new OfficeOfferRolePermission($this->db))->allows($operatorId, $permission)) {
            return false;
        }
        // Role permissions without a configured data scope must not grant access.
        require_once __DIR__ . '/OfficeOfferRoleScope.php';
        // 'own' is intentionally denied until ownership is enforced on offer queries.
        return in_array((new OfficeOfferRoleScope($this->db))->forOperator($operatorId, 'offers'),
            ['assigned', 'all'], true);
    }
}
