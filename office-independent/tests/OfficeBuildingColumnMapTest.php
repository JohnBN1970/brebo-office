<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingColumnMap.php';

use Brebo\Office\Buildings\OfficeBuildingColumnMap as Map;

$test = Map::detect(['id', 'name', 'address_line', 'postal_code', 'city', 'code']);
if ($test['variant'] !== 'office_test' || $test['name'] !== 'name') {
    throw new RuntimeException('Office-test schema detection failed');
}
$legacy = Map::detect(['id', 'building_name', 'street_address', 'postal_code', 'city', 'active']);
if ($legacy['variant'] !== 'legacy') {
    throw new RuntimeException('Legacy schema detection failed');
}
try {
    Map::detect(['id', 'name', 'city']);
    throw new RuntimeException('Incomplete schema accepted');
} catch (DomainException $expected) {
}
echo "OfficeBuildingColumnMapTest OK\n";
