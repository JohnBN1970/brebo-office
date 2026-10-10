<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyMappingValidator;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyMappingValidator.php';

$validator = new WebsiteCrmLegacyMappingValidator();
$valid = $validator->validate(['brebo_contact' => [17 => 501, 18 => 'office-502']]);
if ($valid['brebo_contact'][17] !== 501 || $valid['brebo_contact'][18] !== 'office-502') {
  throw new RuntimeException('Approved CRM mappings changed unexpectedly.');
}
foreach ([
  ['brebo_contact' => [17 => 501, 18 => 501]],
  ['unresolved' => [17 => 501]],
  ['brebo_contact' => [0 => 501]],
  ['brebo_contact' => [17 => 0]],
] as $bad) {
  $rejected = false;
  try {
    $validator->validate($bad);
  }
  catch (InvalidArgumentException) {
    $rejected = true;
  }
  if (!$rejected) {
    throw new RuntimeException('Ambiguous or invalid CRM mapping accepted.');
  }
}
echo "Legacy CRM mapping validation checks passed.\n";
