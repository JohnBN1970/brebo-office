<?php
declare(strict_types=1);

// Static regression checks for the Office dashboard wiring. No DB or secrets required.
$source = file_get_contents(__DIR__ . '/../public/office-home.php');
if ($source === false) { fwrite(STDERR, "Office home missing\n"); exit(1); }
$required = [
  "Dashboard · Mijn werkplek",
  "Relaties & CRM",
  "Projecten",
  "Calculatie & offertes",
  "Financiën",
  "Organisatie",
  "Communicatie",
  "['Mail', null, false]",
  "OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED",
  "OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED",
  "OFFICE_TEST_CRM_ENABLED",
  "currentOperator()",
  'htmlspecialchars($label',
];
foreach ($required as $item) {
  if (!str_contains($source, $item)) {
    fwrite(STDERR, "Missing navigation/security marker: $item\n");
    exit(1);
  }
}
if (!str_contains($source, 'if ($active && is_string($url))')) {
  fwrite(STDERR, "Disabled module guard missing\n"); exit(1);
}
echo "Office navigation static guards OK\n";
