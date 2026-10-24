<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Crm/OfficeCrmSchemaInventory.php';
use Brebo\Office\Crm\OfficeCrmSchemaInventory;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "OfficeCrmSchemaInventoryTest SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
// SQLite does not implement MySQL INFORMATION_SCHEMA. Simulate its read-only
// responses to test inventory behavior without touching any real database.
class InventoryStatement extends PDOStatement {
  public array $rows = [];
  public string $name = '';
  protected function __construct() {}
  public function execute(?array $params = null): bool {
    $this->name = (string)($params[':table_name'] ?? '');
    return true;
  }
  public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
    return $this->rows[$this->name] ?? false;
  }
}
class InventoryDb extends PDO {
  public array $rows = [];
  public function __construct() {}
  public function prepare(string $query, array $options = []): PDOStatement|false {
    $stmt = new InventoryStatement();
    $stmt->rows = $this->rows;
    return $stmt;
  }
}
$fake = new InventoryDb();
$result = OfficeCrmSchemaInventory::inspect($fake);
if ($result['ready'] || count($result['missing']) !== 10 || $result['existing'] !== []) {
  throw new RuntimeException('Missing tables must be detected');
}
$fake->rows['office_crm_companies'] = ['TABLE_NAME' => 'office_crm_companies', 'ENGINE' => 'MyISAM'];
$result = OfficeCrmSchemaInventory::inspect($fake);
if ($result['ready'] || count($result['missing']) !== 9 || count($result['existing']) !== 1
  || !str_contains(implode(';', $result['issues']), 'niet InnoDB')) {
  throw new RuntimeException('Existing incompatible table must fail closed');
}
try {
  OfficeCrmSchemaInventory::requireVerified($fake);
  throw new RuntimeException('Unverified schema was approved');
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'Unverified schema was approved') throw $e;
}
echo "OfficeCrmSchemaInventoryTest OK\n";
