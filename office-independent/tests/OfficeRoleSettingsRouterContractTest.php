<?php

declare(strict_types=1);

$router = file_get_contents(__DIR__ . '/../deployment/hostinger/office-test-index.php');
$handler = file_get_contents(__DIR__ . '/../public/office-role-settings.php');
if (!str_contains($router, "'/office-test/instellingen/rollen' => 'office-role-settings.php'")) {
    throw new RuntimeException('Office-test settings route not dispatched');
}
foreach ([
    "OFFICE_ROLE_SETTINGS_ENABLED",
    "OFFICE_CRM_SESSION_MODE",
    "currentOperator()",
    "OfficeRoleSettingsEndpoint",
    "office_role_settings_csrf",
] as $guard) {
    if (!str_contains($handler, $guard)) {
        throw new RuntimeException('Settings route guard missing: ' . $guard);
    }
}
echo "OfficeRoleSettingsRouterContractTest: PASS\n";
