<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Intake/OfficeMount.php';
require_once __DIR__ . '/../src/Intake/OfficeNavigation.php';

use Brebo\Office\Intake\OfficeMount;
use Brebo\Office\Intake\OfficeNavigation;

putenv('OFFICE_BASE_PATH=/office-test');
putenv('OFFICE_ROLE_SETTINGS_ENABLED=enabled');
if (OfficeMount::route('instellingen/rollen') !== '/office-test/instellingen/rollen') {
    throw new RuntimeException('Settings mount URL incorrect');
}
$html = OfficeNavigation::mainV2('/office-test/instellingen/rollen');
if (!str_contains($html, 'Rollen en rechten') || !str_contains($html, 'aria-current="page"')) {
    throw new RuntimeException('Settings navigation not active');
}
putenv('OFFICE_ROLE_SETTINGS_ENABLED=disabled');
if (str_contains(OfficeNavigation::mainV2('/office-test/'), 'Rollen en rechten')) {
    throw new RuntimeException('Disabled settings link advertised');
}
echo "OfficeRoleSettingsNavigationTest: PASS\n";
