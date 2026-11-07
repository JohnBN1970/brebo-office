<?php
declare(strict_types=1);

// Static cross-repository contract guard. Does not contact staging or enable Calc.
$root = dirname(__DIR__);
$router = file_get_contents($root . '/deployment/hostinger/office-test-index.php');
$endpoint = file_get_contents($root . '/public/office-calc-project-context.php');
$signed = file_get_contents($root . '/src/Calculation/OfficeCalcSignedRequest.php');
if (!is_string($router) || !is_string($endpoint) || !is_string($signed)) {
    throw new RuntimeException('Missing Calc context source');
}
foreach ([
    "office-calc-project-context.php",
    "/api/workbench/v1/projects/",
    "calculation-context",
] as $expected) {
    if (!str_contains($router, $expected)) {
        throw new RuntimeException('Calc context route missing: ' . $expected);
    }
}
foreach ([
    "brebo-office-calc-context-v1",
    "OfficeCalcSignedRequest::verify",
    "OfficeCalcRequestReplayRepository",
    "office_projects",
    "office_project_buildings",
    "office_buildings",
    "office_calc_session_grants",
    "office_operator_sessions",
    "office_project_memberships",
    "g.revoked_at IS NULL",
    "OFFICE_CALC_ENABLED",
    "OFFICE_CALC_SHARED_SECRET",
    "Cache-Control",
] as $expected) {
    if (!str_contains($endpoint, $expected)) {
        throw new RuntimeException('Calc context contract missing: ' . $expected);
    }
}
foreach (['id', 'code', 'title', 'status', 'client_name', 'client_organization', 'project_kind', 'disciplines', 'description', 'buildings'] as $field) {
    if (!str_contains($endpoint, "'" . $field . "' =>")) {
        throw new RuntimeException('Calc project field missing: ' . $field);
    }
}
if (!str_contains($signed, "method === 'GET'") || !str_contains($signed, "calculation-context")) {
    throw new RuntimeException('Calc context signed GET not enforced');
}
echo "CALC_PROJECT_CONTEXT_CONTRACT=PASS\n";
