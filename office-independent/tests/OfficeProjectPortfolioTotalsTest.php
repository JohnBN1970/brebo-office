<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPortfolioTotals.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPortfolioView.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectPageStructure.php';
require_once dirname(__DIR__) . '/src/Project/OfficeProjectTrafficLight.php';

use Brebo\Office\Project\OfficeProjectPortfolioView;

$projects = [['id'=>1,'code'=>'A','title'=>'Test','status'=>'preparation','building_count'=>0]];
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$html = OfficeProjectPortfolioView::render($projects, '/office-test', ['procurement_budget_cents'=>123456]);
$assert(str_contains($html, '€ 1.234,56') && str_contains($html, 'Vastgestelde inkoopbegroting'), 'Locked procurement total missing');
$html = OfficeProjectPortfolioView::render($projects, '/office-test');
$assert(str_contains($html, 'Niet beschikbaar'), 'Missing totals must be unavailable');
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'contract_value_cents'=>129995, 'procurement_budget_cents'=>null,
    'approved_cumulative_seconds'=>23400,
]);
$assert(str_contains($html, '€ 1.299,95'), 'Contract total missing');
$assert(str_contains($html, '6,50 uur'), 'Cumulative approved labour hours missing');
$assert(str_contains($html, 'Arbeidsuren · cumulatief goedgekeurd'), 'Cumulative KPI label missing');
$assert(!str_contains($html, 'Geplande uren (geselecteerde week)'), 'Weekly hours widget must not return to portfolio');
$assert(!str_contains($html, '€ 0,00'), 'Missing procurement data must not be fabricated as zero');
$html = OfficeProjectPortfolioView::render($projects, '/office-test', [
    'approved_cumulative_seconds'=>-3600,
]);
$assert(!str_contains($html, '-1,00 uur'), 'Invalid cumulative approved hours must fail closed');
$assert(str_contains($html, 'Niet beschikbaar'), 'Invalid approved hours must show unavailable');
echo "PORTFOLIO_TOTALS_OK\n";
