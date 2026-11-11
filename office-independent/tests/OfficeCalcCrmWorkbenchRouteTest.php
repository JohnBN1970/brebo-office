<?php
declare(strict_types=1);
// Static route contract: fail CI if the independent CRM Calc workbench loses
// its session/CSRF protection or if deployment path wiring is omitted.
$root=dirname(__DIR__);
$workbench=file_get_contents($root.'/public/office-calc-crm-workbench.php');
$launch=file_get_contents($root.'/public/office-calc-crm-draft-launch.php');
$preflight=file_get_contents($root.'/deployment/hostinger/audit-office-calc-crm-router-readonly.php');
$router=file_get_contents($root.'/deployment/hostinger/office-test-index.php');
if (!is_string($router) || !str_contains($router,"'/office-test/calc/crm/werkbank'") || !str_contains($router,"'/office-test/calc/crm/launch'")) throw new RuntimeException('Office-test CRM Calc routes not mounted');
if (!str_contains($router,"'/office-test/calculatie'")
    || !str_contains($workbench,'action="/office-test/calculatie"')
    || !str_contains($workbench,"Location: /office-test/calculatie?draft_id=")) {
    throw new RuntimeException('Unified Office-test calculation path not mounted');
}
if (!str_contains($router,"'/public/office-calc-crm-workbench.php'") || !str_contains($router,"'/public/office-calc-crm-draft-launch.php'")) throw new RuntimeException('CRM Calc route handlers not mounted');
if (!is_string($workbench)||!is_string($launch)||!is_string($preflight)) throw new RuntimeException('CRM Calc route missing');
foreach ([
    'OFFICE_BASE_PATH','OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED','OFFICE_PUBLIC_HOST',
    'HTTP_ORIGIN','REQUEST_METHOD','WebsiteOfficeCrmSessionRepository',
    'canEditCrm','OfficeCalcCrmDraftLineRepository','hash_equals',
    'Content-Security-Policy','draft_id','unit_cost_cents'
] as $required) {
    if (!str_contains($workbench,$required)) throw new RuntimeException('Workbench contract missing: '.$required);
}
foreach (['HTTP_ORIGIN','REQUEST_METHOD','OfficeCalcCrmLaunchIssuer','hash_equals','draft_id'] as $required) {
    if (!str_contains($launch,$required)) throw new RuntimeException('Launch contract missing: '.$required);
}
foreach (['office-calc-crm-workbench.php','office-calc-crm-draft-launch.php','CRM_CALC_ROUTER_READONLY_CONTRACT'] as $required) {
    if (!str_contains($preflight,$required)) throw new RuntimeException('Router audit contract missing: '.$required);
}
if (str_contains($workbench,'Drupal') && !str_contains($workbench,'not Drupal')) {
    throw new RuntimeException('Unexpected Drupal dependency');
}
echo "CRM_CALC_WORKBENCH_ROUTE_CONTRACT=PASS\n";
