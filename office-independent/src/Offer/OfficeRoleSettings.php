<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;
use DomainException;

/** Central role settings write model; caller must enforce settings.manage. */
final class OfficeRoleSettings
{
    private const PERMISSIONS = ['read', 'create', 'update', 'issue', 'record_acceptance', 'link_change', 'manage'];
    private const SCOPES = ['none', 'own', 'assigned', 'all'];

    public function __construct(private readonly PDO $db)
    {
    }

    public function setModule(int $roleId, string $module, bool $enabled, array $permissions, string $scope): void
    {
        // Only modules with an implemented permission contract can be configured.
        if ($module !== 'offers') {
            throw new DomainException('Module not yet configurable');
        }
        if ($roleId < 1 || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $module)
            || !in_array($scope, self::SCOPES, true)
            || count(array_filter($permissions, 'is_string')) !== count($permissions)
            || array_diff($permissions, self::PERMISSIONS)
            || (!$enabled && ($permissions !== [] || $scope !== 'none'))
            || ($enabled && ($permissions === [] || $scope === 'none'))) {
            throw new DomainException('Invalid module settings');
        }

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM office_roles WHERE id = ? AND active = 1 FOR UPDATE');
            $lock->execute([$roleId]);
            if ($lock->fetchColumn() === false) {
                throw new DomainException('Active role required');
            }
            $remove = $this->db->prepare('DELETE FROM office_role_permissions WHERE role_id = ? AND module = ?');
            $remove->execute([$roleId, $module]);
            $removeScope = $this->db->prepare('DELETE FROM office_role_scopes WHERE role_id = ? AND module = ?');
            $removeScope->execute([$roleId, $module]);
            if ($enabled) {
                $insert = $this->db->prepare('INSERT INTO office_role_permissions (role_id, module, permission) VALUES (?, ?, ?)');
                foreach (array_unique($permissions) as $permission) {
                    $insert->execute([$roleId, $module, $permission]);
                }
                $insertScope = $this->db->prepare('INSERT INTO office_role_scopes (role_id, module, scope_key) VALUES (?, ?, ?)');
                $insertScope->execute([$roleId, $module, $scope]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
