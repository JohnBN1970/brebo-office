<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__).'/public/office-projects.php');
if (!is_string($page)) throw new RuntimeException('Office projects page unavailable.');
foreach ([
  "if (\$inzetView)",
  "OFFICE_TEST_SUPPLIER_WEEKS_ENABLED",
  "OFFICE_TEST_SUPPLIER_WEEKS_SCHEMA_VERIFIED",
  "OnSiteSupplierWeekReadRepository",
  "listVisible((int)\$operator->operatorId, \$authorizedProjectIds)",
  "Alleen ter inzage.",
] as $expected) {
  if (!str_contains($page,$expected)) {
    throw new RuntimeException('Missing isolated supplier-week page gate: '.$expected);
  }
}
if (str_contains($page,'->submit(') || str_contains($page,'->append(')) {
  throw new RuntimeException('Read-only supplier week page must not submit approvals.');
}
echo "Office Inzet supplier week page contract OK\n";
