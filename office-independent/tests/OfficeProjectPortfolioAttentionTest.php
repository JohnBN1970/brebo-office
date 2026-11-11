<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Project/OfficeProjectPageStructure.php';
require_once __DIR__ . '/../src/Project/OfficeProjectTrafficLight.php';
require_once __DIR__ . '/../src/Project/OfficeProjectPortfolioView.php';

use Brebo\Office\Project\OfficeProjectPortfolioView;

$projects = [
    ['id' => 1, 'code' => 'T-1', 'title' => 'Project met knelpunt',
     'status' => 'active', 'building_count' => 1,
     'plan_items' => [['title' => 'Gevel', 'status' => 'critical', 'critical_note' => 'Levering vertraagd']]],
    ['id' => 2, 'code' => 'T-2', 'title' => 'Project zonder planning',
     'status' => 'preparation', 'building_count' => 0],
];
$html = OfficeProjectPortfolioView::render($projects, '/office-test');
foreach (['Projecten die aandacht vragen', 'Levering vertraagd', 'Onbekend', 'Afgesloten', 'Op schema (planning)',
          '/office-test/projecten/detail?id=1', 'Met planningsregels'] as $required) {
    if (!str_contains($html, $required)) throw new RuntimeException('Missing: ' . $required);
}
if (str_contains($html, '2</strong> Op schema')) {
    throw new RuntimeException('Missing planning must not count as on track');
}
echo "OFFICE_PROJECT_PORTFOLIO_ATTENTION_OK\n";
