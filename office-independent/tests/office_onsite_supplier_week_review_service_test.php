<?php

declare(strict_types=1);

foreach (['OnSiteSupplierWeekReviewDecision','OnSiteSupplierWeekReviewStore','OnSiteSupplierWeekReviewService'] as $name) {
  require_once dirname(__DIR__).'/src/Inzet/'.$name.'.php';
}

use Brebo\Office\Inzet\OnSiteSupplierWeekReviewService;
use Brebo\Office\Inzet\OnSiteSupplierWeekReviewStore;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "Supplier week review service integration skipped (pdo_sqlite unavailable)\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_inzet_supplier_week_imports (id INTEGER PRIMARY KEY, supplier_id INTEGER, source_sha256 TEXT)');
$db->exec('CREATE TABLE office_inzet_supplier_week_decisions (id INTEGER PRIMARY KEY AUTOINCREMENT, import_id INTEGER, reviewer_id INTEGER, action TEXT, reason TEXT, source_sha256 TEXT)');
$hash = str_repeat('a',64);
$db->prepare('INSERT INTO office_inzet_supplier_week_imports VALUES (1,3,?)')->execute([$hash]);
$service = new OnSiteSupplierWeekReviewService(new OnSiteSupplierWeekReviewStore($db));
$workbench = ['supplier_id'=>3,'can_approve'=>true];
try {
  $service->submit(1,$workbench,7,[4],'approve','',$hash);
  throw new RuntimeException('Unauthorized supplier approved.');
} catch (DomainException $expected) {}
$record = $service->submit(1,$workbench,7,[3],'approve','',$hash);
if ($record['decision_id'] !== 1 || $record['review_status'] !== 'approved') {
  throw new RuntimeException('Authorized review was not persisted.');
}
try {
  $service->submit(1,['supplier_id'=>3,'can_approve'=>false],7,[3],'approve','',$hash);
  throw new RuntimeException('Unresolved hours approved.');
} catch (DomainException $expected) {}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_supplier_week_decisions')->fetchColumn() !== 1) {
  throw new RuntimeException('Blocked review mutated audit log.');
}
echo "OnSite supplier week review service OK\n";
