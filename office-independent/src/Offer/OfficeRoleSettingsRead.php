<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use DomainException;
use PDO;

/** Read model for the central role settings screen; no public route is registered here. */
final class OfficeRoleSettingsRead
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function rolesForManager(int $operatorId): array
    {
        if ($operatorId < 1) {
            throw new DomainException('Settings management access required');
        }
        $access = $this->db->prepare(
            "SELECT 1 FROM office_module_permissions p
             JOIN office_operator_accounts a ON a.id = p.operator_id AND a.active = 1
             WHERE p.operator_id = :id AND p.module = 'settings'
               AND p.permission = 'manage' LIMIT 1"
        );
        $access->execute([':id' => $operatorId]);
        if ($access->fetchColumn() === false) {
            throw new DomainException('Settings management access required');
        }
        $roles = $this->db->query('SELECT id, label FROM office_roles WHERE active = 1 ORDER BY label')->fetchAll(PDO::FETCH_ASSOC);
        $permissions = $this->db->query("SELECT role_id, module, permission FROM office_role_permissions WHERE module = 'offers'")->fetchAll(PDO::FETCH_ASSOC);
        $scopes = $this->db->query("SELECT role_id, module, scope_key FROM office_role_scopes WHERE module = 'offers'")->fetchAll(PDO::FETCH_ASSOC);
        $byRole = [];
        foreach ($permissions as $grant) {
            $role = (int) $grant['role_id'];
            $module = (string) $grant['module'];
            $byRole[$role][$module]['enabled'] = true;
            $byRole[$role][$module]['permissions'][] = (string) $grant['permission'];
        }
        foreach ($scopes as $scope) {
            $role = (int) $scope['role_id'];
            $module = (string) $scope['module'];
            $byRole[$role][$module]['scope'] = (string) $scope['scope_key'];
        }
        // Show configurable modules even before a role has any grants.
        // The role can then be enabled from an initially empty database.
        $catalog = ['offers'];
        foreach ($roles as &$role) {
            $stored = $byRole[(int) $role['id']] ?? [];
            $role['modules'] = [];
            foreach ($catalog as $module) {
                $role['modules'][$module] = array_merge([
                    'enabled' => false,
                    'permissions' => [],
                    'scope' => 'none',
                ], $stored[$module] ?? []);
            }
        }
        unset($role);
        return $roles;
    }
}
