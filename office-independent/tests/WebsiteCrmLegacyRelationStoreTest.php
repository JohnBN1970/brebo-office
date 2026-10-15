<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyRelationStore;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyRelationStore.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('MySQL test DSN missing.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TABLE IF EXISTS office_crm_legacy_relation');
$db->exec(WebsiteCrmLegacyRelationStore::schema());
$store = new WebsiteCrmLegacyRelationStore($db);
$record = ['legacy_node_id' => 42, 'field' => 'field_brebo_opp_contact_ref', 'target_type' => 'brebo_contact', 'target_id' => 17, 'office_target_id' => 'office-contact-501'];
if ($store->persist([$record]) !== 1 || $store->persist([$record]) !== 0) {
  throw new RuntimeException('CRM relation import must be idempotent.');
}
$rejected = false;
try {
  $store->persist([$record, array_replace($record, ['office_target_id' => 'wrong-contact'])]);
}
catch (RuntimeException) {
  $rejected = true;
}
if (!$rejected || (int) $db->query('SELECT COUNT(*) FROM office_crm_legacy_relation')->fetchColumn() !== 1) {
  throw new RuntimeException('Conflicting relation import was not rolled back.');
}
echo "Independent CRM relation persistence checks passed.\n";
