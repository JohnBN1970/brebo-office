<?php
declare(strict_types=1);
$preflight = file_get_contents(dirname(__DIR__) . '/deployment/hostinger/office-inzet-test-preflight.php');
if (!is_string($preflight)) throw new RuntimeException('Hostinger preflight unavailable');
foreach ([
  'OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED',
  'OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED',
  'office-inzet-personnel-number.php',
  'OfficeInzetPersonnelProfileRepository.php',
  'OfficeInzetPersonnelProfileAction.php',
  'OfficeInzetPersonnelProfileCsrf.php',
  'OfficeInzetPersonnelNumberForm.php',
  '/office-test/inzet/medewerker/personeelsnummer',
] as $required) {
  if (!str_contains($preflight, $required)) throw new RuntimeException('Missing personnel preflight gate: ' . $required);
}
echo "Hostinger personnel profile release preflight gates OK\n";
