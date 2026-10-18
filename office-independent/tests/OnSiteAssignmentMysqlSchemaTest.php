<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Inzet/OnSiteAssignmentStore.php';
use Brebo\Office\Inzet\OnSiteAssignmentStore;

final class RecordingMysqlPdo extends PDO {
  public array $queries = [];
  public function __construct() {}
  public function setAttribute(int $attribute, mixed $value): bool { return true; }
  public function getAttribute(int $attribute): mixed {
    return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : null;
  }
  public function exec(string $statement): int|false {
    $this->queries[] = $statement;
    return 0;
  }
}
$db = new RecordingMysqlPdo();
(new OnSiteAssignmentStore($db))->migrate();
if (count($db->queries) !== 3) {
  throw new RuntimeException('Expected three MySQL tables');
}
foreach ($db->queries as $sql) {
  if (str_contains($sql, 'TEXT PRIMARY KEY') || !str_contains($sql, 'CREATE TABLE IF NOT EXISTS')) {
    throw new RuntimeException('Invalid MySQL schema');
  }
}
echo "OnSiteAssignmentMysqlSchemaTest OK\n";
