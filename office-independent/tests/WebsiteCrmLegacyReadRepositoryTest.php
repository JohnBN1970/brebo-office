<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyReadRepository;
use Brebo\Office\Intake\WebsiteCrmLegacyImportRepository;
use Brebo\Office\Intake\WebsiteCrmLegacyPartyStore;
use Brebo\Office\Intake\WebsiteCrmLegacyStageEventStore;
use Brebo\Office\Intake\WebsiteCrmLegacyRelationStore;

foreach (['WebsiteCrmLegacyReadRepository', 'WebsiteCrmLegacyExportValidator', 'WebsiteCrmLegacyImportRepository', 'WebsiteCrmLegacyPartyStore', 'WebsiteCrmLegacyStageEventStore', 'WebsiteCrmLegacyRelationStore'] as $class) {
  require_once __DIR__ . '/../src/Intake/' . $class . '.php';
}
$db = new PDO(getenv('OFFICE_INTAKE_TEST_DSN'), getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['office_crm_legacy_opportunity','office_crm_legacy_party','office_crm_legacy_stage_event','office_crm_legacy_relation'] as $table) {
  $db->exec('DROP TABLE IF EXISTS ' . $table);
}
foreach ([WebsiteCrmLegacyImportRepository::schema(), WebsiteCrmLegacyPartyStore::schema(), WebsiteCrmLegacyStageEventStore::schema(), WebsiteCrmLegacyRelationStore::schema()] as $schema) {
  $db->exec($schema);
}
(new WebsiteCrmLegacyImportRepository($db))->import([['legacy_node_id' => 42, 'title' => 'Kans A', 'stage' => 'Lead', 'owner_uid' => 1, 'website_request_id' => null]]);
(new WebsiteCrmLegacyPartyStore($db))->import([['party_type' => 'brebo_contact', 'legacy_node_id' => 17, 'title' => 'Contact A', 'fields' => []]]);
(new WebsiteCrmLegacyStageEventStore($db))->import([['legacy_event_node_id' => 81, 'legacy_opportunity_node_id' => 42, 'from_stage' => 'Lead', 'to_stage' => 'Kans', 'occurred_at' => '2026-10-01T10:00:00']]);
(new WebsiteCrmLegacyRelationStore($db))->persist([['legacy_node_id' => 42, 'field' => 'field_brebo_opp_contact_ref', 'target_type' => 'brebo_contact', 'target_id' => 17, 'office_target_id' => 'office-contact-501']]);
$read = new WebsiteCrmLegacyReadRepository($db);
if (count($read->opportunities()) !== 1 || count($read->parties('brebo_contact')) !== 1
  || count($read->history(42)) !== 1 || count($read->relations(42)) !== 1) {
  throw new RuntimeException('Independent CRM read projection lost migrated data.');
}
echo "Independent CRM read projection checks passed.\n";
