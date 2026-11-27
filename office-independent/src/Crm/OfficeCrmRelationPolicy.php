<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use InvalidArgumentException;

/** CRM roles are independent: a prospect may also be a supplier or customer. */
final class OfficeCrmRelationPolicy {
    public const KINDS = ['organization', 'person'];
    public const ROLES = ['prospect', 'customer', 'supplier'];

    /** Stable storage codes; Dutch presentation is independent of historical keys. */
    public static function roleLabel(string $role): string {
        return match ($role) {
            'prospect' => 'Geïnteresseerde',
            'customer' => 'Klant',
            'supplier' => 'Leverancier',
            default => $role,
        };
    }

    public static function kindLabel(string $kind): string {
        return match ($kind) {
            'organization' => 'Bedrijf',
            'person' => 'Particulier',
            default => $kind,
        };
    }

    /** Pure eligibility check. Missing or unknown evidence must never archive a customer. */
    public static function canAutoArchive(string $kind, array $roles, array $evidence): bool {
        if ($kind !== 'person' || !in_array('customer', $roles, true)) return false;
        foreach (['all_orders_and_projects_completed', 'all_invoices_paid',
            'no_open_leads_or_deals', 'no_open_actions_or_restpoints',
            'no_open_complaints', 'no_active_maintenance'] as $condition) {
            if (($evidence[$condition] ?? null) !== true) return false;
        }
        return true;
    }

    public static function validate(string $kind, string $name, array $roles): array {
        $name = trim($name);
        if (!in_array($kind, self::KINDS, true) || $name === '' || strlen($name) > 255) {
            throw new InvalidArgumentException('Invalid CRM relation');
        }
        if (!$roles || count($roles) !== count(array_unique($roles))) {
            throw new InvalidArgumentException('Relation roles must be unique and nonempty');
        }
        foreach ($roles as $role) {
            if (!is_string($role) || !in_array($role, self::ROLES, true)) {
                throw new InvalidArgumentException('Invalid CRM relation role');
            }
        }
        return ['relation_kind' => $kind, 'display_name' => $name, 'roles' => array_values($roles)];
    }
}
