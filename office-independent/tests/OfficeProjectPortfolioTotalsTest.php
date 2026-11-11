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
echo "PORTFOLIO_TOTALS_CONTRACT_OK\n";
