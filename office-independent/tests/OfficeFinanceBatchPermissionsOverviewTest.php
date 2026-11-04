<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionCatalog.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionsView.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionsOverview.php';

use Brebo\Office\Finance\OfficeFinanceBatchPermissionsOverview as Overview;
use Brebo\Office\Finance\OfficeFinanceBatchPermissionCatalog as Catalog;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('1',1),('2',0)");
$db->exec("INSERT INTO office_module_permissions VALUES
 ('1','finance','payment_batch.create'),
 ('1','finance','payment_batch.create'),
 ('1','finance','payment_batch.review'),
 ('1','finance','other'),
 ('1','crm','payment_batch.release'),
 ('2','finance','payment_batch.release')");
$view = new Overview($db);
$checks=0;
$assert=static function(bool $ok,string $message) use (&$checks): void {if (!$ok) throw new RuntimeException($message); ++$checks;};
$one=$view->forOperator('1');
$assert($one['active'] === true,'Active operator');
$assert(count($one['permissions'])===2,'Deduplicated allowlisted grants');
$assert(in_array(Catalog::CREATE,$one['permissions'],true),'Create grant');
$assert(in_array(Catalog::REVIEW,$one['permissions'],true),'Review grant');
$assert(!in_array(Catalog::RELEASE,$one['permissions'],true),'CRM grant not inherited');
$assert(substr_count($view->renderForOperator('1'),'<td>Toegekend</td>')===2,'Rendered status');
$assert($view->forOperator('2')['permissions']===[],'Inactive operator denied');
$assert($view->forOperator('unknown')['permissions']===[],'Missing operator denied');
$assert($view->forOperator('')['permissions']===[],'Empty operator denied');
$assert($view->forOperator(str_repeat('x',129))['permissions']===[],'Oversized operator denied');
$db->exec('DROP TABLE office_module_permissions');
$assert($view->forOperator('1')['permissions']===[],'Database error denies');
echo "OfficeFinanceBatchPermissionsOverviewTest OK ($checks checks)\n";
