<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyPartyStore.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyStageEventStore.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyRelatedImportCommand.php';

use Brebo\Office\Intake\WebsiteCrmLegacyRelatedImportCommand;

$fixture = tempnam(sys_get_temp_dir(), 'crm_related_');
if ($fixture === false) {
  throw new RuntimeException('Could not create test fixture.');
}
try {
  $contact = ['bundle'=>'brebo_contact','legacy_node_id'=>765001,'title'=>'Contact','fields'=>[]];
  file_put_contents($fixture, json_encode([$contact], JSON_THROW_ON_ERROR));
  putenv('OFFICE_CRM_IMPORT_CONFIRM');
  if (WebsiteCrmLegacyRelatedImportCommand::run(['import-related', 'brebo_contact', $fixture]) !== 0) {
    throw new RuntimeException('Valid contact dry run rejected.');
  }
  file_put_contents($fixture, json_encode([$contact, $contact], JSON_THROW_ON_ERROR));
  if (WebsiteCrmLegacyRelatedImportCommand::run(['import-related', 'brebo_contact', $fixture]) !== 1) {
    throw new RuntimeException('Duplicate contact accepted.');
  }
  file_put_contents($fixture, json_encode([$contact], JSON_THROW_ON_ERROR));
  if (WebsiteCrmLegacyRelatedImportCommand::run(['import-related', 'brebo_organization', $fixture]) !== 1) {
    throw new RuntimeException('Mixed CRM party type accepted.');
  }
  $event = [
    'bundle'=>'brebo_opportunity_event',
    'legacy_node_id'=>765002,
    'title'=>'Stage changed',
    'fields'=>[
      'field_brebo_event_opp_ref'=>[['target_id'=>'765003']],
      'field_brebo_event_from_stage'=>[['value'=>'lead']],
      'field_brebo_event_to_stage'=>[['value'=>'qualified']],
      'field_brebo_event_datetime'=>[['value'=>'2026-10-11T10:00:00']],
    ],
  ];
  file_put_contents($fixture, json_encode([$event], JSON_THROW_ON_ERROR));
  if (WebsiteCrmLegacyRelatedImportCommand::run(['import-related', 'brebo_opportunity_event', $fixture]) !== 0) {
    throw new RuntimeException('Valid stage event dry run rejected.');
  }
  unset($event['fields']['field_brebo_event_opp_ref']);
  file_put_contents($fixture, json_encode([$event], JSON_THROW_ON_ERROR));
  if (WebsiteCrmLegacyRelatedImportCommand::run(['import-related', 'brebo_opportunity_event', $fixture]) !== 1) {
    throw new RuntimeException('Stage event without opportunity accepted.');
  }
}
finally {
  unlink($fixture);
}
echo "Related CRM import dry-run checks passed.\n";
