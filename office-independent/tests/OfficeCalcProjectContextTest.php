<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Calculation/OfficeCalcProjectContext.php';

use Brebo\Office\Calculation\OfficeCalcProjectContext;

$record = [
  'id' => 42,
  'code' => 'P-42',
  'title' => 'Project 42',
  'status' => 'active',
  'client_name' => 'Example client',
  'client_organization' => ['id' => 12, 'title' => 'Client BV'],
  'project_kind' => 'renovation',
  'disciplines' => ['glass'],
  'description' => '',
  'buildings' => [['id' => 13, 'title' => 'Building A']],
];
$context = OfficeCalcProjectContext::fromRecord(42, $record);
if ($context['contract'] !== 'brebo-office-calc-context-v1'
  || $context['project']['buildings'][0]['id'] !== 13) {
  throw new RuntimeException('Valid context not preserved.');
}
foreach ([
  [43, $record],
  [42, array_merge($record, ['title' => ''])],
  [42, array_merge($record, ['buildings' => [['id' => 0, 'title' => 'X']]])],
  [42, array_merge($record, ['client_organization' => ['id' => '12', 'title' => 'Client BV']])],
] as [$id, $invalid]) {
  try {
    OfficeCalcProjectContext::fromRecord($id, $invalid);
  } catch (DomainException) {
    continue;
  }
  throw new RuntimeException('Invalid context accepted.');
}
echo "Office Calc project context validation passed.\n";
