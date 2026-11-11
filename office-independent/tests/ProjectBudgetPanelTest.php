<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/ProjectBudgetPanel.php';
use Brebo\Office\WorkingBudget\ProjectBudgetPanel;

$html=ProjectBudgetPanel::render([
    'project_id'=>42,
    'kpis'=>['baseline_cents'=>120000,'awarded_cents'=>null,'actual_cents'=>null,
        'open_commitment_cents'=>null,'forecast_cents'=>null,'variance_cents'=>null],
    'cost_lines'=>[['code'=>'<script>', 'cost_type'=>'labor','activity_ref'=>'A&B','baseline_cents'=>120000]],
    'source_status'=>['baseline'=>'locked','finance'=>'<script>alert(1)</script>'],
]);
if (!str_contains($html,'€ 1.200,00')
    || !str_contains($html,'Nog niet beschikbaar')
    || str_contains($html,'<script>')
    || !str_contains($html,'&lt;script&gt;')
    || !str_contains($html,'Begrotingsregels')
    || !str_contains($html,'Kostenverdeling')
    || !str_contains($html,'Materiaal')
    || !str_contains($html,'Arbeid')
    || !str_contains($html,'A&amp;B')) {
    throw new RuntimeException('Budget panel display or escaping failed.');
}
echo "PROJECT_BUDGET_PANEL_TEST=PASS\n";
