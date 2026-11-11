<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=file_get_contents($root.'/public/office-calc-crm-browser-handoff.php');
$endpoint=file_get_contents($root.'/public/office-calc-crm-draft-launch.php');
$router=file_get_contents($root.'/deployment/hostinger/office-test-index.php');
if (!is_string($page)||!is_string($endpoint)||!is_string($router)) throw new RuntimeException('CRM Calc handoff source missing');
foreach ([
    "OFFICE_CALC_CRM_V2_LAUNCH_ENABLED",
    "OFFICE_CALC_CRM_V2_GRANT_VALIDATION_ENABLED",
    "OFFICE_CALC_CRM_V2_GRANT_VALIDATION_VERIFIED",
    "OFFICE_CALC_CRM_APP_ORIGIN",
    "WebsiteOfficeCrmSessionRepository",
    "OfficeCalcCrmDraftAccess",
    "office_project_csrf",
    "Content-Security-Policy",
    "form.method='POST'",
    "form.enctype='application/x-www-form-urlencoded'",
    "form.submit()",
    "/api/launch/consume",
    "Office controleert toegang",
] as $required) {
    if (!str_contains($page,$required)) throw new RuntimeException('Handoff contract missing: '.$required);
}
if (!str_contains($router,"'/office-test/calc/crm/open'")
    || !str_contains($router,"office-calc-crm-browser-handoff.php"))
    throw new RuntimeException('CRM browser handoff not mounted');
if (!str_contains($endpoint,"issueSigned(")
    || !str_contains($endpoint,"OFFICE_CALC_CRM_V2_GRANT_VALIDATION_VERIFIED"))
    throw new RuntimeException('Signed CRM launch endpoint not gated');
if (preg_match('/location\.(?:href|replace)\s*\([^)]*token/', $page)
    || preg_match('/[?&]token=/', $page)) throw new RuntimeException('Launch token leaked into URL');
echo "OFFICE_CALC_CRM_BROWSER_POST_HANDOFF=PASS\n";
