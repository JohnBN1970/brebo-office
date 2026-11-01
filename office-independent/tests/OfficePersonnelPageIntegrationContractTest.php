<?php
declare(strict_types=1);
$page = file_get_contents(dirname(__DIR__) . '/public/office-personnel.php');
$form = file_get_contents(dirname(__DIR__) . '/src/Inzet/OfficePersonnelDetailsReadForm.php');
if (!is_string($page) || !is_string($form)) throw new RuntimeException('Missing personnel sources');
foreach ([
  'OfficePersonnelDetailsAction(', 'OfficePersonnelDetailsRepository($db)',
  'OfficePersonnelDetailsReadForm::render(', 'OfficeInzetPersonnelProfileCsrf::token(',
  "OFFICE_TEST_PERSONNEL_DETAILS_SCHEMA_VERIFIED",
  '($_SERVER[\'REQUEST_METHOD\'] ?? \'\') === \'POST\'',
] as $expected) {
  if (!str_contains($page, $expected)) throw new RuntimeException('Personnel integration missing: ' . $expected);
}
if (!str_contains($form, 'action="/office-test/personeel/medewerkers"')) {
  throw new RuntimeException('Form action does not match routed page');
}
echo "Personnel page integration contract OK\n";
