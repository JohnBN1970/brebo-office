<?php
declare(strict_types=1);
$source = file_get_contents(dirname(__DIR__) . '/public/office-inzet-personnel-number.php');
if (!is_string($source)) throw new RuntimeException('Personnel POST handler missing');
foreach ([
  "REQUEST_METHOD", "OFFICE_BASE_PATH", "OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED",
  "OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED", "OFFICE_PUBLIC_HOST",
  "HTTP_ORIGIN", "currentOperator()", "OfficeInzetPersonnelProfileAction",
  "Location: /office-test/projecten?view=inzet", "303"
] as $required) {
  if (!str_contains($source, $required)) throw new RuntimeException('Missing personnel POST security boundary: ' . $required);
}
if (str_contains($source, '$_POST[\'operator_id\']')) throw new RuntimeException('Client-supplied operator identity accepted');
echo "Personnel POST route security boundaries present\n";
