<?php
declare(strict_types=1);

$path=__DIR__.'/../src/Inspection/OfficeInspectionFindingAction.php';
$source=file_get_contents($path);
if (!is_string($source)) throw new RuntimeException('finding_action_missing');
foreach ([
  "i.status='draft'",
  "m.permission='manage'",
  'office_inspection_report_findings',
  'include_in_report',
  'finding_not_editable',
  'beginTransaction()',
  'rollBack()'
] as $needle) {
  if (!str_contains($source,$needle)) throw new RuntimeException('missing_finding_contract: '.$needle);
}
echo "OfficeInspectionFindingActionContractTest OK\n";
