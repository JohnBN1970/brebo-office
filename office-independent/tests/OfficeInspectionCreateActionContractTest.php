<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../src/Inspection/OfficeInspectionCreateAction.php');
if (!is_string($source)) throw new RuntimeException('missing_inspection_action');
foreach ([
  'inspection_access_denied',
  "m.permission='manage'",
  'office_project_buildings',
  'office_operator_accounts',
  'beginTransaction()',
  'rollBack()',
  'OfficeInspectionReportConfiguration::normalize'
] as $needle) {
  if (!str_contains($source,$needle)) throw new RuntimeException('missing_inspection_contract: '.$needle);
}
echo "OfficeInspectionCreateActionContractTest OK\n";
