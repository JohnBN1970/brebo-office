<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmCutoverAudit;

require_once __DIR__ . '/../src/Intake/WebsiteCrmCutoverAudit.php';

$a = 'aaaaaaaa-1111-4111-8111-111111111111';
$b = 'bbbbbbbb-2222-4222-8222-222222222222';
$c = 'cccccccc-3333-4333-8333-333333333333';
$audit = new WebsiteCrmCutoverAudit();
$result = $audit->compare([$a, $a, $b], [$a, $c, $c]);
if ($result['missing_in_crm'] !== [$b]
  || $result['missing_in_staging'] !== [$c]
  || $result['duplicate_staging'] !== [$a]
  || $result['duplicate_crm'] !== [$c]) {
  throw new RuntimeException('CRM cutover reconciliation failed.');
}
$matched = $audit->compare([$a, $b], [strtoupper($b), $a]);
if ($matched !== ['missing_in_crm' => [], 'missing_in_staging' => [], 'duplicate_staging' => [], 'duplicate_crm' => []]) {
  throw new RuntimeException('Equivalent CRM identities did not match.');
}
try {
  $audit->compare(['aaaaaaaa'], [$a]);
  throw new RuntimeException('Short Drupal title-prefix identifier accepted.');
}
catch (InvalidArgumentException) {
  // Full UUID required.
}
echo "CRM cutover identity audit checks passed.\n";
