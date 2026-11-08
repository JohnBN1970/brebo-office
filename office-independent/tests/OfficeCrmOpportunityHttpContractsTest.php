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
if (str_contains($nav,"[['Aanvragen','/office-test/crm/aanvragen']]")) throw new RuntimeException('Legacy requests advertised');
if (!str_contains($nav,"[['Verkoopkansen','/office-test/crm/verkoopkansen']]")) throw new RuntimeException('Verkoopkansen missing from navigation');
$installer=file_get_contents($root.'/deployment/hostinger/install-office-crm-opportunities-test.php');
foreach (['OFFICE_CRM_OPPORTUNITIES_SCHEMA_INSTALL','u213420663_office_test','PHP_SAPI','target_exists_inspect_first'] as $gate)
    if (!str_contains($installer,$gate)) throw new RuntimeException('Installer safety gate missing: '.$gate);
echo "CRM_OPPORTUNITY_HTTP_CONTRACTS_OK\n";
