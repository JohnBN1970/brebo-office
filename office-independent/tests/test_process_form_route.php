<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$router=file_get_contents($root.'/public/index.php');
$page=file_get_contents($root.'/public/office-process-forms.php');
if (!str_contains($router,"'/office-test/procesformulieren' => 'office-process-forms.php'")) {
    throw new RuntimeException('Office-test router does not expose the catalog');
}
if (!str_contains($router,"'/office-test/instellingen/organisatie/projectrechten' => 'office-project-permission-settings.php'")) {
    throw new RuntimeException('Project rights route was lost during process form integration');
}
foreach ([
    "OFFICE_TEST_PROCESS_FORMS_CATALOG_ENABLED",
    "OFFICE_BASE_PATH",
    "OFFICE_PUBLIC_HOST",
    "WebsiteOfficeCrmCookieSessionProvider",
    "currentOperator()",
    "office_operator_accounts",
    "OfficeProcessFormCatalog::all()",
    "REQUEST_METHOD",
] as $required) {
    if (!str_contains($page,$required)) throw new RuntimeException('Missing read-only catalog gate: '.$required);
}
if (preg_match('/\\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\\s+(?:INTO\\s+|FROM\\s+|TABLE\\s+)?office_process_form_/i',$page)) {
    throw new RuntimeException('Catalog endpoint must not write forms');
}
echo "Process form Office-test router and session gates OK\n";
