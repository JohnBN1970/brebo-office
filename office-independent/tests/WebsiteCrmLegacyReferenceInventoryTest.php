<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyReferenceInventory;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyReferenceInventory.php';

$inventory = new WebsiteCrmLegacyReferenceInventory();
$references = $inventory->collect([[
  'legacy_node_id' => 42,
  'fields' => [
    'field_brebo_opp_contact_ref' => [['target_id' => '17']],
    'field_brebo_opp_organization_ref' => [['target_id' => 19]],
    'field_brebo_opp_owner' => [['target_id' => 3]],
    'field_custom_relation' => [['target_id' => 77]],
    'field_brebo_opp_stage' => [['value' => 'Lead']],
  ],
]]);
if (count($references) !== 4 || $references[0]['target_type'] !== 'brebo_contact'
  || $references[1]['target_type'] !== 'brebo_organization'
  || $references[2]['target_type'] !== 'user'
  || $references[3]['target_type'] !== 'unresolved') {
  throw new RuntimeException('Legacy CRM references were lost or guessed.');
}
try {
  $inventory->collect([['legacy_node_id' => 42, 'fields' => ['field_brebo_opp_contact_ref' => [['target_id' => 'bad']]]]]);
}
catch (InvalidArgumentException) {
  echo "Legacy CRM reference inventory checks passed.\n";
  exit(0);
}
throw new RuntimeException('Invalid legacy CRM target ID accepted.');
