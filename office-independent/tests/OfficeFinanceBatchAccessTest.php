<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionCatalog.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchAccess.php';

use Brebo\Office\Finance\OfficeFinanceBatchAccess as Access;
use Brebo\Office\Finance\OfficeFinanceBatchPermissionCatalog as Catalog;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  throw new RuntimeException("pdo_sqlite is required for Finance ACL regression tests");
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT NOT NULL, module TEXT NOT NULL, permission TEXT NOT NULL, PRIMARY KEY(operator_id,module,permission))');
$db->exec("INSERT INTO office_operator_accounts VALUES ('1',1),('2',0),('3',1)");
$db->exec("INSERT INTO office_module_permissions VALUES ('1','finance','payment_batch.create'),('1','finance','payment_batch.review'),('2','finance','payment_batch.release'),('3','crm','payment_batch.release')");
$checks=0;
$assert=static function(bool $ok,string $message) use (&$checks): void {if (!$ok) throw new RuntimeException($message); ++$checks;};
$one=new Access($db,'1');
$assert($one->allows(Catalog::CREATE),'Active creator must be permitted');
$assert($one->allows(Catalog::REVIEW),'Active reviewer must be permitted');
$assert(!$one->allows(Catalog::RELEASE),'No release grant must deny');
$assert(!(new Access($db,'2'))->allows(Catalog::RELEASE),'Inactive account must deny');
$assert(!(new Access($db,'3'))->allows(Catalog::RELEASE),'Other module grant must deny');
$assert(!(new Access($db,'missing'))->allows(Catalog::CREATE),'Unknown account must deny');
$assert(!(new Access($db,''))->allows(Catalog::CREATE),'Empty identity must deny');
$assert(!$one->allows('finance.payment_batch.unknown'),'Unknown permission must deny');
$assert(!$one->allows('payment_batch.create'),'Unqualified permission must deny');
$db->exec("INSERT INTO office_module_permissions VALUES ('3','finance','payment_batch.release')");
$assert((new Access($db,'3'))->allows(Catalog::RELEASE),'Explicit release grant must allow');
$db->exec("UPDATE office_operator_accounts SET active=0 WHERE id='3'");
$assert(!(new Access($db,'3'))->allows(Catalog::RELEASE),'Deactivation must revoke');
// An authenticated operator may hold both creation and release grants.
// This tests permission resolution only, not payment release execution.
$db->exec("INSERT INTO office_module_permissions VALUES ('1','finance','payment_batch.release')");
$assert($one->allows(Catalog::CREATE) && $one->allows(Catalog::RELEASE), 'Independent create and release grants may coexist');
$db->exec('DROP TABLE office_module_permissions');
$assert(!$one->allows(Catalog::CREATE),'Missing permissions table must fail closed');
echo "OfficeFinanceBatchAccessTest OK ($checks checks)\n";
