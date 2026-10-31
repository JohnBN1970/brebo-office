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

    public function allows(int|string $operatorId, string $permission): bool
    {
        if ((is_int($operatorId) ? $operatorId < 1 : trim($operatorId) === '') || !in_array($permission, [
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
               AND table_name IN ('office_roles', 'office_user_roles', 'office_role_permissions', 'office_role_scopes')"
        );
        $schema->execute();
        if ((int) $schema->fetchColumn() !== 4) {
            return false;
        }
        // Permission and enforceable scope must belong to the SAME active role.
        // Otherwise a scope from an unrelated role could elevate a permission.
        // 'own' remains denied until ownership is enforced at query level.
        $roleGrant = $this->db->prepare(
            "SELECT 1 FROM office_user_roles ur
             JOIN office_roles r ON r.id = ur.role_id AND r.active = 1
             JOIN office_role_permissions rp ON rp.role_id = r.id
             JOIN office_role_scopes rs ON rs.role_id = r.id AND rs.module = rp.module
             JOIN office_operator_accounts a ON a.id = ur.operator_id AND a.active = 1
             WHERE ur.operator_id = :operator_id AND rp.module = 'offers'
               AND rp.permission = :permission
               AND rs.scope_key IN ('assigned', 'all') LIMIT 1"
        );
        $roleGrant->execute([':operator_id' => $operatorId, ':permission' => $permission]);
        return $roleGrant->fetchColumn() !== false;
    }
}
