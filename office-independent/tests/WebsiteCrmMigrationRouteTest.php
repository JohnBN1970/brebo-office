<?php

declare(strict_types=1);

$script = file_get_contents(__DIR__ . '/../public/crm-migration.php');
if ($script === false) {
  throw new RuntimeException('CRM migration route missing.');
}
foreach ([
  "hash_equals(\$expected, \$provided)",
  "OFFICE_CRM_WORKBENCH_TOKEN",
  "REQUEST_METHOD",
  "/brebo-internal/crm/migration",
  "Cache-Control: no-store, private",
  "Content-Security-Policy:",
  "PDO::ATTR_EMULATE_PREPARES => false",
] as $required) {
  if (!str_contains($script, $required)) {
    throw new RuntimeException('CRM route missing security safeguard: ' . $required);
  }
}
echo "CRM migration route security contract checks passed.\n";
