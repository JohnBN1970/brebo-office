<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPortfolioTotals.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPortfolioView.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPageStructure.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectTrafficLight.php';

use Brebo\Office\Project\OfficeProjectPortfolioTotals;
use Brebo\Office\Project\OfficeProjectPortfolioView;

$projects = [['id'=>1,'code'=>'A','title'=>'Test','status'=>'preparation','building_count'=>0]];
$html = OfficeProjectPortfolioView::render($projects, '/office-test', ['procurement_budget_cents'=>123456]);
if (!str_contains($html, '€ 1.234,56') || !str_contains($html, 'Vastgestelde inkoopbegroting')) {
    throw new RuntimeException('Locked procurement total missing');
}
$html = OfficeProjectPortfolioView::render($projects, '/office-test');
if (!str_contains($html, 'Niet beschikbaar')) throw new RuntimeException('Missing totals must be unavailable');
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'contract_value_cents' => 129995,
    'contract_covered' => 1,
    'contract_total' => 1,
    'procurement_covered' => 0,
    'procurement_total' => 1,
]);
if (!str_contains($html, '€ 1.299,95')
    || !str_contains($html, 'Geaccepteerde contracten: 1 van 1 projecten.')
    || !str_contains($html, 'Vastgestelde inkoopbegrotingen: 0 van 1 toegankelijke projecten.')) {
    throw new RuntimeException('Accepted contract amount or source coverage missing');
}
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'contract_value_cents' => null,
    'contract_covered' => 0,
    'contract_total' => 1,
]);
if (str_contains($html, '€ 0,00') || !str_contains($html, 'Geaccepteerde contracten: 0 van 1 projecten.')) {
    throw new RuntimeException('Incomplete contract coverage must not display zero');
}
$manyProjects = array_fill(0, 100, $projects[0]);
$html = OfficeProjectPortfolioView::render($manyProjects, '/office-test', ['accessible_project_count' => 125]);
if (!str_contains($html, '100 van 125 toegankelijke projecten')) {
    throw new RuntimeException('Truncated project list must be disclosed');
}
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'planned_week_seconds' => 23400, 'planned_week_label' => '2026-W46',
]);
if (!str_contains($html, '6,50 uur') || !str_contains($html, '2026-W46')) {
    throw new RuntimeException('Planned weekly hours KPI missing');
}
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'planned_week_seconds' => -3600, 'planned_week_label' => '2026-W46',
]);
if (str_contains($html, '-1,00 uur') || !str_contains($html, 'geannuleerde diensten uitgesloten')) {
    throw new RuntimeException('Negative planned hours or cancelled-shift disclosure regression');
}
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'planned_week_seconds' => 3600, 'planned_week_label' => '2026-W01',
]);
if (!str_contains($html, '/office-test/projecten?week=2025-W52')
    || !str_contains($html, '/office-test/projecten?week=2026-W02')
    || !str_contains($html, 'Vorige week')
    || !str_contains($html, 'Volgende week')) {
    throw new RuntimeException('ISO week navigation must cross year boundaries');
}
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'planned_week_seconds' => 3600, 'planned_week_label' => '2026-W53',
]);
if (!str_contains($html, '/office-test/projecten?week=2027-W01')) {
    throw new RuntimeException('Week 53 must navigate to next ISO year');
}
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'planned_week_seconds' => 3600, 'planned_week_label' => '2026-W46',
]);
if (!str_contains($html, 'Geplande uren (geselecteerde week)')
    || !str_contains($html, 'geselecteerde ISO-week')
    || !str_contains($html, '</div><p class="office-portfolio-total-scope">')
    || !str_contains($html, '</nav><section class="office-portfolio-attention"')) {
    throw new RuntimeException('Selected week label or KPI grid layout regression');
}
echo "PORTFOLIO_TOTALS_CONTRACT_OK\n";
