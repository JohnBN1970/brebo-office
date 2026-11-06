<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingPdokSchemaMigration.php';

use Brebo\Office\Buildings\OfficeBuildingPdokSchemaMigration;

function check(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$reflection = new ReflectionMethod(OfficeBuildingPdokSchemaMigration::class, 'apply');
check($reflection->isPublic() && $reflection->isStatic(), 'migration entrypoint missing');
$source = file_get_contents(dirname(__DIR__) . '/src/Buildings/OfficeBuildingPdokSchemaMigration.php');
check(is_string($source), 'source unavailable');
foreach (['latitude', 'longitude', 'bag_id'] as $field) {
  check(str_contains($source, 'ADD COLUMN ' . $field), 'missing additive field: ' . $field);
}
check(substr_count($source, 'ADD COLUMN') === 3, 'unexpected DDL additions');
check(!preg_match('/\b(DROP|TRUNCATE|DELETE)\s+(TABLE|FROM|office_buildings)/i', $source), 'destructive statement');
echo "PDOK_SCHEMA_MIGRATION_STATIC_CHECK_OK\n";
