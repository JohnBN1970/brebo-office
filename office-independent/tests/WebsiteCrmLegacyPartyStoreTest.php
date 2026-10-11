<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyPartyStore;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyPartyStore.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('MySQL test DSN missing.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TABLE IF EXISTS office_crm_legacy_party');
$db->exec(WebsiteCrmLegacyPartyStore::schema());
$store = new WebsiteCrmLegacyPartyStore($db);
$contact = ['party_type' => 'brebo_contact', 'legacy_node_id' => 17, 'title' => 'Contact A', 'fields' => ['field_phone' => [['value' => '123']]]];
$organization = ['party_type' => 'brebo_organization', 'legacy_node_id' => 19, 'title' => 'Organization B', 'fields' => []];
if ($store->import([$contact, $organization]) !== 2 || $store->import([$contact, $organization]) !== 0) {
  throw new RuntimeException('Legacy CRM party import is not idempotent.');
}
$rejected = false;
try {
  $store->import([$organization, array_replace($contact, ['title' => 'Wrong contact'])]);
}
catch (RuntimeException) {
  $rejected = true;
}
if (!$rejected || (int) $db->query('SELECT COUNT(*) FROM office_crm_legacy_party')->fetchColumn() !== 2) {
  throw new RuntimeException('Conflicting CRM party import must roll back.');
}
echo "Independent CRM party persistence checks passed.\n";
