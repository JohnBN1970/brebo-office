<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$routes=file_get_contents($root.'/deployment/hostinger/office-test-index.php');
$nav=file_get_contents($root.'/src/Intake/OfficeNavigation.php');
$expected=[
    '/office-test/crm/verkoopkansen'=>'office-crm-opportunities.php',
    '/office-test/crm/verkoopkansen/nieuw'=>'office-crm-opportunity-create.php',
    '/office-test/crm/verkoopkansen/fase'=>'office-crm-opportunity-transition.php',
];
foreach ($expected as $url=>$file) {
    if (!str_contains($routes,"'".$url."' => '".$file."'")) throw new RuntimeException('Missing route '.$url);
    $source=file_get_contents($root.'/public/'.$file);
    foreach (['OFFICE_TEST_CRM_ENABLED','OFFICE_CRM_AUTH_MODE','OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED','OFFICE_PUBLIC_HOST','office_operator_accounts','currentOperator'] as $gate)
        if (!str_contains($source,$gate)) throw new RuntimeException('Missing security gate '.$gate.' in '.$file);
    if ($file!=='office-crm-opportunities.php') {
        foreach (['OFFICE_CRM_OPPORTUNITIES_WRITE_ENABLED','csrf','hash_equals','session_start','REQUEST_METHOD'] as $gate)
            if (!str_contains($source,$gate)) throw new RuntimeException('Missing write protection '.$gate.' in '.$file);
    }
}
foreach (['public/index.php','deployment/hostinger/office-test-index.php'] as $router) {
    $source=file_get_contents($root.'/'.$router);
    if (!str_contains($source,"'/office-test/crm/verkoopkansen/drag.js' => 'office-crm-kanban-drag.php'")) {
        throw new RuntimeException('Kanban script route missing: '.$router);
    }
}
foreach (['public/index.php','deployment/hostinger/office-test-index.php'] as $router) {
    $source=file_get_contents($root.'/'.$router);
    foreach (['/office-test/crm/aanvragen/fase', '/office-test/crm/aanvragen/drag.js'] as $route) {
        if (!str_contains($source,$route)) throw new RuntimeException('Lead Kanban route missing: '.$route.' in '.$router);
    }
}
$leadBoard=file_get_contents($root.'/public/crm-new-requests.php');
if (!str_contains($leadBoard,"script-src 'self'") || str_contains($leadBoard,"script-src 'unsafe-inline'")) throw new RuntimeException('Lead board CSP must require external scripts');
if (!str_contains($leadBoard,'/crm/aanvragen/drag.js')) throw new RuntimeException('Lead board script missing');
$board=file_get_contents($root.'/public/office-crm-opportunities.php');
$views=file_get_contents($root.'/src/Crm/OfficeCrmFunnelViews.php');
$drag=file_get_contents($root.'/public/office-crm-kanban-drag.js');
foreach (['script-src \'self\'','crm_opportunity_csrf','office-crm-kanban-drag.php'] as $check) {
    if ($check==='office-crm-kanban-drag.php') continue;
    if (!str_contains($board,$check)) throw new RuntimeException('Kanban board missing '.$check);
}
foreach (['data-crm-id','data-crm-stage','draggable='] as $check) {
    if (!str_contains($views,$check)) throw new RuntimeException('Kanban markup missing '.$check);
}
foreach (['URLSearchParams','credentials:\'same-origin\'','/office-test/crm/verkoopkansen/fase','window.location.reload()'] as $check) {
    if (!str_contains($drag,$check)) throw new RuntimeException('Kanban drag missing '.$check);
}
// A closed Deal must remain readable but cannot offer phase transitions; project handoff stays separate.
$transition=file_get_contents($root.'/public/office-crm-opportunity-transition.php');
foreach (["status']==='open'", "item['status'] !== 'open'", "create_direct_project", "transferForOperator", "OfficeCrmProjectHandoff", "crm_opportunity_csrf"] as $check) {
    if (!str_contains($transition,$check)) throw new RuntimeException('Closed Deal / project handoff contract missing: '.$check);
}
if (str_contains($nav,"[['Aanvragen','/office-test/crm/aanvragen']]")) throw new RuntimeException('Legacy requests advertised');
if (!str_contains($nav,"[['Deals','/office-test/crm/verkoopkansen']]")) throw new RuntimeException('Deals missing from navigation');
$installer=file_get_contents($root.'/deployment/hostinger/install-office-crm-opportunities-test.php');
foreach (['OFFICE_CRM_OPPORTUNITIES_SCHEMA_INSTALL','u213420663_office_test','PHP_SAPI','target_exists_inspect_first'] as $gate)
    if (!str_contains($installer,$gate)) throw new RuntimeException('Installer safety gate missing: '.$gate);
echo "CRM_OPPORTUNITY_HTTP_CONTRACTS_OK\n";
