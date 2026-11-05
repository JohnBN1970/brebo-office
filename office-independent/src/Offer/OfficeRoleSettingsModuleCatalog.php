<?php
declare(strict_types=1);

namespace Brebo\Office\Offer;

/**
 * Explicit allowlist for central role-settings forms.
 * This catalog does not grant effective operator permissions.
 */
final class OfficeRoleSettingsModuleCatalog
{
    private const MODULES = [
        'offers' => [
            'read' => 'Bekijken',
            'create' => 'Aanmaken',
            'update' => 'Wijzigen',
            'issue' => 'Uitbrengen',
            'record_acceptance' => 'Acceptatie registreren',
            'link_change' => 'Meerwerk koppelen',
            'manage' => 'Beheren',
        ],
        'finance' => [
            'payment_batch.create' => 'Betaalbatch aanmaken',
            'payment_batch.review' => 'Betaalbatch beoordelen',
            'payment_batch.release' => 'Betaalbatch vrijgeven',
        ],
    ];

    public static function modules(): array
    {
        return array_keys(self::MODULES);
    }

    public static function labels(string $module): array
    {
        return self::MODULES[$module] ?? [];
    }

    public static function allows(string $module, array $permissions): bool
    {
        if (!isset(self::MODULES[$module])) {
            return false;
        }
        foreach ($permissions as $permission) {
            if (!is_string($permission) || !array_key_exists($permission, self::MODULES[$module])) {
                return false;
            }
        }
        return true;
    }
}
