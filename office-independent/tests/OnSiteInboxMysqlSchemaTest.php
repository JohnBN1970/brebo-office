<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Inzet/OnSiteEventInbox.php';
use Brebo\Office\Inzet\OnSiteEventInbox;

final class RecordingInboxMysqlPdo extends PDO {
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
$db = new RecordingInboxMysqlPdo();
(new OnSiteEventInbox($db))->migrate();
if (count($db->queries) !== 1 || !str_contains($db->queries[0], 'event_id VARCHAR(36) PRIMARY KEY')
  || !str_contains($db->queries[0], 'INDEX onsite_event_employee_time')) {
  throw new RuntimeException('MySQL inbox schema missing unique event key or index');
}
echo "OnSiteInboxMysqlSchemaTest OK\n";
