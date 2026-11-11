<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/src/Calculation/OfficeNativeProjectHandoffPreflight.php');
if (!is_string($source)) throw new RuntimeException('Missing project handoff preflight');
foreach ([
    'information_schema.COLUMNS',
    'office_projects',
    'office_project_memberships',
    'office_project_buildings',
    'office_crm_calculation_acceptances',
    'office_calc_work_budget_baselines',
    'office_calc_work_budget_lines',
    "'ready' => \$missing === []",
] as $required) {
    if (!str_contains($source, $required)) throw new RuntimeException('Missing project handoff preflight requirement: ' . $required);
}
if (preg_match('/\b(INSERT|UPDATE|DELETE|ALTER|DROP|CREATE)\b/i', $source)) {
    throw new RuntimeException('Preflight must be read only');
}
echo "NATIVE_PROJECT_HANDOFF_PREFLIGHT_TEST=PASS\n";
