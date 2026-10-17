<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteSupplierWeekReviewStore.php';

use Brebo\Office\Inzet\OnSiteSupplierWeekReviewStore;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
  echo "OnSite supplier week review store integration skipped (pdo_sqlite unavailable)\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_inzet_supplier_week_imports (id INTEGER PRIMARY KEY, supplier_id INTEGER, source_sha256 TEXT)');
$db->exec('CREATE TABLE office_inzet_supplier_week_decisions (id INTEGER PRIMARY KEY AUTOINCREMENT, import_id INTEGER, reviewer_id INTEGER, action TEXT, reason TEXT, source_sha256 TEXT)');
$hash = str_repeat('a',64);
$db->prepare('INSERT INTO office_inzet_supplier_week_imports VALUES (1,3,?)')->execute([$hash]);
$decision = ['supplier_id'=>3,'reviewer_id'=>7,'action'=>'approve','reason'=>'',
  'source_sha256'=>$hash,'recorded'=>false,'invoice_eligible'=>false];
$store = new OnSiteSupplierWeekReviewStore($db);
$id = $store->append(1,$decision);
if ($id !== 1 || (int)$db->query('SELECT COUNT(*) FROM office_inzet_supplier_week_decisions')->fetchColumn() !== 1) {
  throw new RuntimeException('Review decision not appended.');
}
try {
  $store->append(1,array_replace($decision,['supplier_id'=>4]));
  throw new RuntimeException('Cross-supplier review accepted.');
} catch (DomainException $expected) {}
try {
  $store->append(1,array_replace($decision,['source_sha256'=>str_repeat('b',64)]));
  throw new RuntimeException('Wrong XLSX source accepted.');
} catch (DomainException $expected) {}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_supplier_week_decisions')->fetchColumn() !== 1) {
  throw new RuntimeException('Rejected review modified audit history.');
}
echo "OnSite supplier week review store OK\n";
