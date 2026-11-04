<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionCatalog.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionsView.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchPermissionsOverview.php';
require_once __DIR__ . '/../src/Finance/OfficeFinanceBatchSettingsPanel.php';

use Brebo\Office\Finance\OfficeFinanceBatchSettingsPanel;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('pdo_sqlite required');
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('admin',1),('staff',1),('disabled',0)");
$db->exec("INSERT INTO office_module_permissions VALUES ('staff','finance','payment_batch.create')");
$panel=new OfficeFinanceBatchSettingsPanel($db);
$checks=0;
$assert=static function(bool $ok,string $message) use (&$checks): void {if (!$ok) throw new RuntimeException($message);++$checks;};
$denied=static function(callable $call) use ($assert): void {
  try {$call();$assert(false,'Expected access denial');}
  catch (DomainException $e) {$assert(true,'Denied');}
};
$denied(fn()=> $panel->render('admin',false,'staff'));
$denied(fn()=> $panel->render('',true,'staff'));
$denied(fn()=> $panel->render('disabled',true,'staff'));
$denied(fn()=> $panel->render('missing',true,'staff'));
$denied(fn()=> $panel->render('admin',true,''));
$html=$panel->render('admin',true,'staff');
$assert(str_contains($html,'Betaalbatchrechten'),'Panel heading');
$assert(str_contains($html,'Medewerker: staff (Actief)'),'Target identity');
$assert(substr_count($html,'<td>Toegekend</td>')===1,'Persisted grant rendered');
$assert(!str_contains($html,'<form'),'No write controls');
$inactive=$panel->render('admin',true,'disabled');
$assert(str_contains($inactive,'Inactief of onbekend'),'Inactive label');
$assert(substr_count($inactive,'Niet toegekend')===3,'Inactive permissions hidden');
$escaped=$panel->render('admin',true,'<script>');
$assert(!str_contains($escaped,'<script>'),'Target identifier escaped');
echo "OfficeFinanceBatchSettingsPanelTest OK ($checks checks)\n";
