<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyExportValidator;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyExportValidator.php';
$validator = new WebsiteCrmLegacyExportValidator();
$id = 'aaaaaaaa-1111-4111-8111-111111111111';
$rows = [
  ['legacy_node_id' => 42, 'title' => 'Europakozijn - aanvraag [aaaaaaaa]', 'stage' => 'Lead', 'owner_uid' => 1, 'website_request_id' => $id],
  ['legacy_node_id' => 43, 'title' => 'Andere kans', 'stage' => 'Offerte', 'owner_uid' => 2, 'website_request_id' => null],
];
$result = $validator->validate($rows);
if ($result['records'] !== 2 || $result['legacy_node_ids'] !== [42, 43] || $result['website_request_ids'] !== [$id]) {
  throw new RuntimeException('CRM export validation did not preserve identities.');
}
foreach ([
  [$rows[0], $rows[0]],
  [array_replace($rows[0], ['website_request_id' => 'aaaaaaaa']), $rows[1]],
  [$rows[0], array_replace($rows[1], ['website_request_id' => $id])],
  [array_replace($rows[0], ['owner_uid' => 0]), $rows[1]],
] as $invalid) {
  try {
    $validator->validate($invalid);
    throw new RuntimeException('Invalid CRM export accepted.');
  }
  catch (InvalidArgumentException) {
    // Correctly rejected.
  }
}
echo "Legacy CRM export validation checks passed.\n";
