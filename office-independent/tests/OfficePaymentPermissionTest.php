<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentPermission.php';
use Brebo\Office\Finance\OfficePaymentPermission;
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_module_permissions (operator_id INTEGER, module TEXT, permission TEXT)');
$db->exec('INSERT INTO office_operator_accounts VALUES (1,1),(2,0),(3,1)');
$db->exec("INSERT INTO office_module_permissions VALUES
  (1,'finance','read'),(1,'finance','draft_edit'),(2,'finance','bank_submit'),
  (3,'crm','bank_submit')");
$permission = new OfficePaymentPermission($db);
foreach ([0,1,2,3] as $id) {
  if ($permission->canSubmit($id)) throw new RuntimeException('Bank submission privilege leaked');
}
$db->exec("INSERT INTO office_module_permissions VALUES (3,'finance','bank_submit')");
if (!$permission->canSubmit(3)) throw new RuntimeException('Explicit bank permission rejected');
$db->exec('UPDATE office_operator_accounts SET active=0 WHERE id=3');
if ($permission->canSubmit(3)) throw new RuntimeException('Inactive operator allowed to submit');
echo "OfficePaymentPermissionTest OK\n";
