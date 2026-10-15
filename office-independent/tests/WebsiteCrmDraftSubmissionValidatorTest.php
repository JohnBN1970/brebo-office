<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmDraftSubmissionValidator;

require_once __DIR__ . '/../src/Intake/WebsiteCrmDraftSubmissionValidator.php';

$validator = new WebsiteCrmDraftSubmissionValidator();
$valid = $validator->validate(['id' => '42', 'version' => '0', 'title' => ' Updated ', 'stage' => 'Lead']);
if ($valid !== ['id' => 42, 'version' => 0, 'title' => 'Updated', 'stage' => 'Lead']) {
  throw new RuntimeException('CRM draft normalization failed.');
}
foreach ([
  ['id' => '-1', 'version' => '0', 'title' => 'X', 'stage' => 'Lead'],
  ['id' => '42', 'version' => '-1', 'title' => 'X', 'stage' => 'Lead'],
  ['id' => '42', 'version' => '0', 'title' => '', 'stage' => 'Lead'],
  ['id' => '42', 'version' => '0', 'title' => 'X', 'stage' => []],
] as $bad) {
  $rejected = false;
  try {
    $validator->validate($bad);
  }
  catch (InvalidArgumentException) {
    $rejected = true;
  }
  if (!$rejected) {
    throw new RuntimeException('Invalid CRM draft submission accepted.');
  }
}
echo "CRM draft submission validation checks passed.\n";
