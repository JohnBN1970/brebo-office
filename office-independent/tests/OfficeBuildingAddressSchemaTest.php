<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingAddressSchema.php';

use Brebo\Office\Buildings\OfficeBuildingAddressSchema as Schema;

$assert = static function (bool $condition, string $message): void {
  if (!$condition) { fwrite(STDERR, $message."\n"); exit(1); }
};

$assert(Schema::floor(null) === null, 'floor must be optional');
$assert(Schema::floor(1) === 1, 'ground floor must be one');
$assert(Schema::floor(-1) === -1, 'basement must be minus one');
Schema::validateAddress(null, null);
Schema::validateAddress(3, null);
Schema::validateAddress(null, 7);
try { Schema::floor(0); fwrite(STDERR, "floor zero accepted\n"); exit(1); }
catch (InvalidArgumentException $expected) {}
try { Schema::validateAddress(null, 0); fwrite(STDERR, "invalid dwelling type accepted\n"); exit(1); }
catch (InvalidArgumentException $expected) {}
$sql = implode("\n", Schema::statements());
foreach (['office_building_addresses', 'office_building_dwelling_types',
  'office_building_address_floors', 'office_building_demarcations',
  'office_building_demarcation_addresses', 'office_building_inventory_items'] as $table) {
  $assert(str_contains($sql, 'CREATE TABLE '.$table), 'missing table '.$table);
}
$assert(str_contains($sql, 'primary_floor SMALLINT NULL'), 'floor not optional in schema');
$assert(str_contains($sql, 'dwelling_type_id BIGINT UNSIGNED NULL'), 'type not optional in schema');
echo "Office building address schema checks OK\n";
