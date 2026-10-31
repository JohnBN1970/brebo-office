<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use DomainException;
use PDO;

/** Framework-independent POST handler; route must supply authenticated actor and CSRF token. */
final class OfficeRoleSettingsController
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function save(int $operatorId, array $post, string $expectedCsrfToken): void
    {
        if ($operatorId < 1 || $expectedCsrfToken === ''
            || !isset($post['csrf_token']) || !is_string($post['csrf_token'])
            || !hash_equals($expectedCsrfToken, $post['csrf_token'])) {
            throw new DomainException('Unauthorized settings request');
        }
        $grant = $this->db->prepare(
            "SELECT 1 FROM office_module_permissions p
             JOIN office_operator_accounts a ON a.id = p.operator_id AND a.active = 1
             WHERE p.operator_id = :operator_id AND p.module = 'settings'
               AND p.permission = 'manage' LIMIT 1"
        );
        $grant->execute([':operator_id' => $operatorId]);
        if ($grant->fetchColumn() === false) {
            throw new DomainException('Settings management access required');
        }
        $roleId = filter_var($post['role_id'] ?? null, FILTER_VALIDATE_INT);
        $module = $post['module'] ?? null;
        $permissions = $post['permissions'] ?? [];
        $scope = $post['scope'] ?? 'none';
        if (!$roleId || !is_string($module) || !is_array($permissions)
            || !is_string($scope) || array_filter($permissions, 'is_string') !== $permissions) {
            throw new DomainException('Invalid settings form');
        }
        $enabled = ($post['enabled'] ?? null) === '1';
        // Disabled module ignores stale form values from collapsed controls.
        (new OfficeRoleSettings($this->db))->setModule(
            $roleId, $module, $enabled,
            $enabled ? $permissions : [],
            $enabled ? $scope : 'none'
        );
    }
}
