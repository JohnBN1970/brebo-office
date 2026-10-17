<?php

declare(strict_types=1);

foreach (['OnSiteSupplierIdentityResolver','OnSiteWeeklyTimesheetControl','OnSiteSupplierWeekReconciliation'] as $name) {
  require_once dirname(__DIR__).'/src/Inzet/'.$name.'.php';
}

use Brebo\Office\Inzet\OnSiteSupplierWeekReconciliation;

$service = new OnSiteSupplierWeekReconciliation();
$approved = [
  ['person_id'=>10,'project_id'=>20,'date'=>'2026-08-24','minutes'=>480],
  ['person_id'=>10,'project_id'=>20,'date'=>'2026-08-25','minutes'=>480],
];
$imported = [
  ['sheet'=>'wk35','cell'=>'D2','person_label'=>'Monteur A','project_label'=>'Project A',
    'date'=>'2026-08-24','minutes'=>480,'review_required'=>false],
  ['sheet'=>'wk35','cell'=>'E2','person_label'=>'Monteur A','project_label'=>'Project A',
    'date'=>'2026-08-25','minutes'=>480,'review_required'=>false],
];
$matched = $service->reconcile($imported,3,['Monteur A'=>10],['Project A'=>20],$approved);
if ($matched['status'] !== 'hours_match' || $matched['review_required']
  || $matched['invoice_eligible'] !== false || count($matched['differences']) !== 2) {
  throw new RuntimeException('Supplier matching or invoice gate incorrect.');
}
$imported[1]['review_required'] = true;
$imported[1]['comment'] = '4 uur op ander project';
$blocked = $service->reconcile($imported,3,['Monteur A'=>10],['Project A'=>20],$approved);
if ($blocked['status'] !== 'requires_review' || count($blocked['import_issues']) !== 1
  || $blocked['differences'][1]['difference_minutes'] !== -480) {
  throw new RuntimeException('Unresolved supplier note not blocking reconciliation.');
}
echo "OnSite supplier week reconciliation OK\n";
