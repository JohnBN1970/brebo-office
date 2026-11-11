<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/WorkingBudgetKpiProjection.php';
use Brebo\Office\WorkingBudget\WorkingBudgetKpiProjection as Kpi;
$missing=Kpi::fromSources(null,null,null);
if ($missing['state']!=='baseline_missing' || $missing['forecast_cents']!==null) throw new RuntimeException('Missing baseline was treated as zero.');
$baseline=['project_id'=>42,'currency'=>'EUR','vat_basis'=>'excluding','lines'=>[
    ['baseline_cents'=>5000],['baseline_cents'=>7000]]];
$partial=Kpi::fromSources($baseline,['project_id'=>42,'awarded_cents'=>4000],null);
if ($partial['baseline_cents']!==12000 || $partial['awarded_cents']!==4000
    || $partial['actual_cents']!==null || $partial['variance_cents']!==null) throw new RuntimeException('Partial KPI projection invalid.');
$finance=['project_id'=>42,'state'=>'reconciled','posted_actual_cents'=>1000,'open_commitment_cents'=>3000];
$joined=Kpi::fromSources($baseline,['project_id'=>42,'awarded_cents'=>4000],$finance);
if ($joined['actual_cents']!==1000 || $joined['open_commitment_cents']!==3000
    || $joined['forecast_cents']!==null) throw new RuntimeException('Unverified forecast published.');
echo "WORKING_BUDGET_KPI_PROJECTION_TEST=PASS\n";
