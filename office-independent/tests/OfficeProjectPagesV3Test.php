<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Project/OfficeProjectPageStructure.php';
require_once __DIR__ . '/../src/Project/OfficeProjectPortfolioView.php';
use Brebo\Office\Project\OfficeProjectPageStructure as S;
use Brebo\Office\Project\OfficeProjectPortfolioView as V;
$rows=[
 ['id'=>1,'code'=>'TEST-2026-001','title'=>'TEST - Renovatie 232 woningen','status'=>'preparation','building_count'=>1],
 ['id'=>2,'code'=>'X','title'=>'<script>alert(1)</script>','status'=>'active','building_count'=>2],
];
assert(S::statusLabel('preparation')==='Voorbereiding');
assert(S::statusLabel('')==='Niet vastgelegd');
assert(S::portfolioSummary($rows)===['projects'=>2,'buildings'=>3,'preparation'=>1,'active'=>1]);
assert(count(S::detailTabs())===5);
$html=V::render($rows,'/office-test');
assert(str_contains($html,'Projectendashboard'));
assert(str_contains($html,'Projectenlijst'));
assert(str_contains($html,'/office-test/projecten/detail?id=1'));
assert(str_contains($html,'Voorbereiding'));
assert(!str_contains($html,'<script>'));
assert(!str_contains($html,'GOTIK'));
echo "PROJECT_PAGES_V3_FOUNDATION_OK\n";
