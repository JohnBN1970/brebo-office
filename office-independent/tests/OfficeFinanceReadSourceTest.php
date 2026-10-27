<?php

declare(strict_types=1);

require __DIR__.'/OfficeFinanceFixture.php';
use Brebo\Office\Finance\OfficeFinanceApplication;
use Brebo\Office\Finance\OfficeFinanceReadConnection;

final class FinanceProtectionProbe extends PDO {
  public array $calls=[];
  public function __construct() {}
  public function exec(string $statement): int|false { $this->calls[]=$statement; return 0; }
  public function beginTransaction(): bool { $this->calls[]='BEGIN'; return true; }
}
$probe=new FinanceProtectionProbe();
OfficeFinanceReadConnection::protect($probe);
financeCheck($probe->calls===['SET SESSION TRANSACTION READ ONLY','BEGIN'],'Read-only protection must precede source transaction');
putenv('OFFICE_FINANCE_READ_PDO_DSN=');
financeCheck(OfficeFinanceReadConnection::fromEnvironment()===null,'Unset source should preserve local adapter');
putenv('OFFICE_FINANCE_READ_PDO_DSN=sqlite::memory:');
try { OfficeFinanceReadConnection::fromEnvironment(); throw new RuntimeException('Unsupported source accepted'); } catch (RuntimeException $e) { financeCheck($e->getMessage()==='Unsupported Finance read source','Unexpected connection error'); }
putenv('OFFICE_FINANCE_READ_PDO_DSN=');
if (getenv('OFFICE_FINANCE_TEST_DSN')) {
  $db=financeFixture();
  OfficeFinanceReadConnection::protect($db);
  financeCheck(array_sum((new OfficeFinanceApplication($db,$db))->read('1')['counts'])===7,'Protected MySQL source not readable');
  try { $db->exec('UPDATE brebo_finance_purchase_invoice SET status=status WHERE 1=0'); throw new RuntimeException('Source allowed DML'); } catch (PDOException $e) { financeCheck(in_array((string)$e->getCode(),['25006','HY000'],true),'Unexpected read-only error'); }
  $db->rollBack();
} else {
  $office=financeFixture(); $source=financeFixture();
  $financeTables=$source->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'brebo_finance_%'")->fetchAll(PDO::FETCH_COLUMN);
  foreach($financeTables as $table) { $office->exec('DROP TABLE '.$table); $source->exec('ALTER TABLE '.$table.' RENAME TO snapshot_'.$table); }
  $source->exec('DELETE FROM office_module_permissions');
  $app=new OfficeFinanceApplication($office,$source,'snapshot_');
  financeCheck(array_sum($app->read('1')['counts'])===7,'Source separation or table prefix broken');
  $office->exec("DELETE FROM office_module_permissions WHERE operator_id='1'");
  try { $app->read('1'); throw new RuntimeException('Source identity overrode Office ACL'); } catch (DomainException) {}
  try { new \Brebo\Office\Finance\OfficeFinanceReadRepository($source,new \Brebo\Office\Finance\OfficeFinanceAccess($office,'4'),null,'bad;prefix'); throw new RuntimeException('Unsafe prefix accepted'); } catch (InvalidArgumentException) {}
}
echo "OFFICE_FINANCE_READ_SOURCE=PASS (separate source, Office ACL, prefix validation, database read-only protection)\n";
