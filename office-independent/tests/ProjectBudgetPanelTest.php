<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/ProjectBudgetPanel.php';
use Brebo\Office\WorkingBudget\ProjectBudgetPanel;

$html=ProjectBudgetPanel::render([
    'project_id'=>42,
    'kpis'=>['baseline_cents'=>120000,'awarded_cents'=>null,'actual_cents'=>null,
        'open_commitment_cents'=>null,'forecast_cents'=>null,'variance_cents'=>null],
    'cost_lines'=>[['code'=>'<script>', 'cost_type'=>'labor','activity_ref'=>'A&B','baseline_cents'=>120000]],
    'labor_evidence'=>['state'=>'exceptions_present','session_count'=>2,'duration_seconds'=>5400,'labor_actual_cents'=>null],
    'source_status'=>['baseline'=>'locked','finance'=>'<script>alert(1)</script>'],
]);
if (!str_contains($html,'€ 1.200,00')
    || !str_contains($html,'Nog niet beschikbaar')
    || str_contains($html,'<script>')
    || !str_contains($html,'&lt;script&gt;')
    || !str_contains($html,'Begrotingsregels')
    || !str_contains($html,'Kostenverdeling')
    || !str_contains($html,'Verplicht')
    || !str_contains($html,'Werkelijk')
    || !str_contains($html,'Prognose')
    || !str_contains($html,'<th scope="row">Totaal</th>')
    || !str_contains($html,'1,50 uur')
    || !str_contains($html,'niet goedgekeurd')
    || !str_contains($html,'afwijkingen in de urenregistratie')
    || !str_contains($html,'Materiaal')
    || !str_contains($html,'Arbeid')
    || !str_contains($html,'A&amp;B')) {
    throw new RuntimeException('Budget panel display or escaping failed.');
}
try {
    ProjectBudgetPanel::render([
        'project_id'=>42,
        'kpis'=>[],
        'source_status'=>[],
        'labor_evidence'=>['state'=>'provisional','session_count'=>1,
            'duration_seconds'=>3600,'labor_actual_cents'=>100],
    ]);
    throw new RuntimeException('Posted labor actuals incorrectly accepted.');
} catch (InvalidArgumentException $expected) {}
try {
    ProjectBudgetPanel::render([
        'project_id'=>42,
        'kpis'=>['baseline_cents'=>120001],
        'source_status'=>['baseline'=>'locked'],
        'cost_lines'=>[['code'=>'L1','cost_type'=>'labor','activity_ref'=>'A',
            'baseline_cents'=>120000]],
    ]);
    throw new RuntimeException('Mismatched locked budget total incorrectly accepted.');
} catch (InvalidArgumentException $expected) {}
$aggregateHtml = ProjectBudgetPanel::render([
    'project_id'=>42,
    'kpis'=>['baseline_cents'=>120000,'awarded_cents'=>45000,
        'actual_cents'=>25000,'forecast_cents'=>null],
    'source_status'=>['baseline'=>'locked'],
    'cost_lines'=>[['code'=>'L1','cost_type'=>'labor','activity_ref'=>'A',
        'baseline_cents'=>120000]],
]);
if (!str_contains($aggregateHtml,'€ 450,00')
    || !str_contains($aggregateHtml,'€ 250,00')
    || !str_contains($aggregateHtml,'<th scope="row">Totaal</th>')) {
    throw new RuntimeException('Verified aggregate costs missing from budget total row.');
}
echo "PROJECT_BUDGET_PANEL_TEST=PASS\\n";
