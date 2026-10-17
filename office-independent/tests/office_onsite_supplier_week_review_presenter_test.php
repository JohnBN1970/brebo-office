<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Inzet/OnSiteSupplierWeekReviewPresenter.php';

use Brebo\Office\Inzet\OnSiteSupplierWeekReviewPresenter;

$view = (new OnSiteSupplierWeekReviewPresenter())->render([
  'supplier_id'=>3,'status'=>'requires_review',
  'onsite_total_minutes'=>480,'supplier_total_minutes'=>540,'can_approve'=>false,
  'day_lines'=>[['date'=>'2026-08-24','person_id'=>10,'project_id'=>20,
    'onsite_minutes'=>480,'supplier_minutes'=>540,'difference_minutes'=>60]],
  'issues'=>[['sheet'=>'<script>alert(1)</script>','cell'=>'D2','reason'=>'source_note_requires_review']],
]);
if (!str_contains($view,'2026-08-24') || !str_contains($view,'540')
  || !str_contains($view,'Goedkeuring geblokkeerd')
  || str_contains($view,'<script>')
  || !str_contains($view,'&lt;script&gt;')) {
  throw new RuntimeException('Supplier review HTML is incorrect or unescaped.');
}
echo "OnSite supplier week review presenter OK\n";
