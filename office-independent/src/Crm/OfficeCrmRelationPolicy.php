<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use InvalidArgumentException;

/** CRM roles are independent: a prospect may also be a supplier or customer. */
final class OfficeCrmRelationPolicy {
    public const KINDS = ['organization', 'person'];
    public const ROLES = ['prospect', 'customer', 'supplier'];

    public static function validate(string $kind, string $name, array $roles): array {
        $name = trim($name);
        if (!in_array($kind, self::KINDS, true) || $name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 255) {
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
