<?php

declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Explicit read-only compatibility check for the two known Office building
 * layouts. Do not silently choose a write schema or migrate data.
 */
final class OfficeBuildingColumnMap
{
    public static function detect(array $columns): array
    {
        $available = [];
        foreach ($columns as $column) {
            if (!is_string($column)) {
                throw new \InvalidArgumentException('invalid_building_columns');
            }
            $available[$column] = true;
        }
        $common = ['id', 'postal_code', 'city'];
        foreach ($common as $column) {
            if (!isset($available[$column])) {
                throw new \DomainException('building_schema_unverified');
            }
        }
        if (isset($available['name'], $available['address_line'])) {
            return ['variant' => 'office_test', 'name' => 'name', 'address' => 'address_line'];
        }
        if (isset($available['building_name'], $available['street_address'], $available['active'])) {
            return ['variant' => 'legacy', 'name' => 'building_name', 'address' => 'street_address'];
        }
        throw new \DomainException('building_schema_unverified');
    }
}
