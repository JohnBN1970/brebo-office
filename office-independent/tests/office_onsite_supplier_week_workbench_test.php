<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteSupplierWeekWorkbench.php';

use Brebo\Office\Inzet\OnSiteSupplierWeekWorkbench;

$builder = new OnSiteSupplierWeekWorkbench();
$base = [
  'supplier_id'=>3,'approved_minutes'=>480,'supplier_minutes'=>480,
  'review_required'=>false,'import_issues'=>[],
  'differences'=>[['person_id'=>10,'project_id'=>20,'date'=>'2026-08-24',
    'approved_minutes'=>480,'supplier_minutes'=>480,'difference_minutes'=>0]],
];
$ok = $builder->build($base);
if ($ok['status'] !== 'awaiting_explicit_approval' || !$ok['can_approve']
  || $ok['approved'] || $ok['invoice_eligible']) {
  throw new RuntimeException('Clean hours automatically approved or incorrectly blocked.');
}
$base['import_issues'] = [['sheet'=>'wk35','cell'=>'D2','reason'=>'source_note_requires_review']];
$blocked = $builder->build($base);
if ($blocked['status'] !== 'requires_review' || $blocked['can_approve']
  || $blocked['issue_count'] !== 1) throw new RuntimeException('Unresolved note not blocking approval.');
$base['import_issues'] = [];
$base['review_required'] = true;
$base['differences'][0]['difference_minutes'] = 60;
$diff = $builder->build($base);
if ($diff['difference_count'] !== 1 || $diff['day_lines'][0]['status'] !== 'hours_difference'
  || $diff['can_approve']) throw new RuntimeException('Daily difference not flagged.');
echo "OnSite supplier week workbench OK\n";
