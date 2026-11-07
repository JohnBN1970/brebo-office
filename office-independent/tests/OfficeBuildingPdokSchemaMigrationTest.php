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
// Check executable SQL literals, excluding comments. Exact allowlist also rejects
// DROP COLUMN, UPDATE, RENAME, MODIFY and similarly prefixed column names.
function safeMigrationSource(string $source): bool {
  $sql = [];
  foreach (token_get_all($source) as $token) {
    if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
      $literal = substr($token[1], 1, -1);
      if (preg_match('/\b(ALTER|DROP|TRUNCATE|DELETE|UPDATE|INSERT|REPLACE|CREATE|RENAME)\b/i', $literal)) $sql[] = $literal;
    }
  }
  sort($sql);
  $expected = [
    'ALTER TABLE office_buildings ADD COLUMN bag_id VARCHAR(128) NULL',
    'ALTER TABLE office_buildings ADD COLUMN latitude DECIMAL(10,7) NULL',
    'ALTER TABLE office_buildings ADD COLUMN longitude DECIMAL(10,7) NULL',
  ];
  sort($expected);
  return $sql === $expected;
}
check(safeMigrationSource($source), 'unexpected SQL in migration');
check(!safeMigrationSource(str_replace('ADD COLUMN latitude ', 'ADD COLUMN latitude_backup ', $source)), 'prefix mutation undetected');
foreach (['ALTER TABLE office_buildings DROP COLUMN address_line', 'DELETE FROM office_buildings', 'TRUNCATE TABLE office_building_zones'] as $bad) {
  check(!safeMigrationSource($source . "\n\$db->exec('" . $bad . "');"), 'destructive mutation undetected');
}

// Execute the real migration against a recording PDO double: no connection and
// no database writes. Verify partial schemas, no-op reruns and failed DDL.
final class MigrationStatement extends PDOStatement {
  public function __construct(private array $columns) {}
  public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->columns; }
}
final class MigrationDatabase extends PDO {
  public array $executed = [];
  public bool $fail = false;
  public function __construct(public array $columns) {}
  public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
    check(str_contains($query, 'information_schema.COLUMNS'), 'unexpected read');
    return new MigrationStatement($this->columns);
  }
  public function exec(string $statement): int|false {
    if ($this->fail) throw new PDOException('simulated DDL failure');
    $this->executed[] = $statement;
    check((bool)preg_match('/^ALTER TABLE office_buildings ADD COLUMN (latitude|longitude|bag_id) /', $statement, $m), 'unexpected write');
    $this->columns[] = $m[1];
    return 0;
  }
}
foreach ([[], ['latitude'], ['longitude', 'bag_id'], ['latitude', 'longitude', 'bag_id']] as $present) {
  $db = new MigrationDatabase(array_merge(['id', 'name', 'address_line'], $present));
  $missing = array_values(array_diff(['latitude', 'longitude', 'bag_id'], $present));
  check(OfficeBuildingPdokSchemaMigration::apply($db) === $missing, 'wrong migration plan');
  check(count($db->executed) === count($missing), 'unexpected DDL count');
  check(OfficeBuildingPdokSchemaMigration::apply($db) === [], 'rerun not idempotent');
  check(count($db->executed) === count($missing), 'rerun wrote DDL');
}
$db = new MigrationDatabase(['id']);
try { OfficeBuildingPdokSchemaMigration::apply($db); throw new LogicException('schema accepted'); }
catch (RuntimeException $e) { check($e->getMessage() === 'unexpected_office_buildings_schema', 'wrong schema failure'); }
check($db->executed === [], 'invalid schema wrote DDL');
$db = new MigrationDatabase(['id', 'name', 'address_line']);
$db->fail = true;
try { OfficeBuildingPdokSchemaMigration::apply($db); throw new LogicException('DDL failure ignored'); }
catch (PDOException $e) { check($db->executed === [], 'failure mutated schema'); }
echo "PDOK_SCHEMA_MIGRATION_STATIC_CHECK_OK\n";
