<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$router=file_get_contents($root.'/deployment/hostinger/office-test-index.php');
$mount=file_get_contents($root.'/src/Intake/OfficeMount.php');
$workbench=file_get_contents($root.'/public/office-calc-crm-workbench.php');
foreach ([
  "'/office-test/calculatie' => 'office-calc-crm-workbench.php'",
  "'/office-test/calculatie/samenwerken' => 'office-calc-crm-workbench.php'",
] as $route) {
  if (!str_contains($router,$route)) throw new RuntimeException('Missing isolated HTTP route: '.$route);
}
foreach (["'calculatie' => '/calculatie'","'calculatie/samenwerken' => '/calculatie/samenwerken'"] as $route) {
  if (!str_contains($mount,$route)) throw new RuntimeException('Missing canonical Office route: '.$route);
}
foreach (['OFFICE_BASE_PATH','OFFICE_CALC_DRAFT_COLLABORATION_ENABLED','OFFICE_CALC_CRM_SCHEMA_VERIFIED','WebsiteOfficeCrmSessionRepository','office_project_csrf'] as $guard) {
  if (!str_contains($workbench,$guard)) throw new RuntimeException('Missing Calc workbench security gate: '.$guard);
}
if (!str_contains($router,"$"."private . '/public/'")) throw new RuntimeException('Router must use private test release');
echo "CALC_UNIFIED_ROUTER_CONTRACT=PASS\n";
