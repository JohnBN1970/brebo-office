<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceAccess.php';
use Brebo\Office\Finance\OfficeFinanceAccess;

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('allowed',1),('finance-only',1),('bank-only',1),('inactive',0),('crm-only',1),('project-manager',1)");
$db->exec("INSERT INTO office_module_permissions VALUES
  ('allowed','finance','read'),('allowed','finance','bank_read'),
  ('finance-only','finance','read'),('bank-only','finance','bank_read'),
  ('inactive','finance','read'),('inactive','finance','bank_read'),
  ('crm-only','crm','read'),('crm-only','crm','bank_read'),
  ('project-manager','projects','manage')");
foreach (['allowed'=>true,'finance-only'=>false,'bank-only'=>false,'inactive'=>false,'crm-only'=>false,'project-manager'=>false,'missing'=>false] as $id=>$expected) {
  if ((new OfficeFinanceAccess($db, $id))->allowsBankRead() !== $expected) {
    throw new RuntimeException('Unexpected bank access for '.$id);
  }
}
$access = new OfficeFinanceAccess($db, 'allowed');
$db->exec("DELETE FROM office_module_permissions WHERE operator_id='allowed' AND permission='bank_read'");
if ($access->allowsBankRead() || !$access->allows()) throw new RuntimeException('Grant revocation not isolated');
if ($access->allows('unknown_permission')) throw new RuntimeException('Unknown permission accepted');
$db->exec("DROP TABLE office_module_permissions");
try {
  $access->allowsBankRead();
  throw new RuntimeException('Missing authorization storage must not grant access');
} catch (PDOException $expected) {}
echo "OFFICE_FINANCE_BANK_ACCESS_OK\n";
