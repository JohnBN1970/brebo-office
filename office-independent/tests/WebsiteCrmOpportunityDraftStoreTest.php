<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmOpportunityDraftStore;

require_once __DIR__ . '/../src/Intake/WebsiteCrmOpportunityDraftStore.php';

$db = new PDO(getenv('OFFICE_INTAKE_TEST_DSN'), getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TABLE IF EXISTS office_crm_opportunity_draft');
$db->exec(WebsiteCrmOpportunityDraftStore::schema());
$db->prepare('INSERT INTO office_crm_legacy_opportunity (legacy_node_id,title,stage,owner_uid,website_request_id,source_json) VALUES (987656,\'Original title\',\'Lead\',1,NULL,\'{}\')')->execute();
$store = new WebsiteCrmOpportunityDraftStore($db);
if ($store->save(987656, 'New title', 'Qualified', 0) !== 1 || $store->save(987656, 'Next title', 'Qualified', 1) !== 2
  || (int) $store->get(987656)['version'] !== 2) {
  throw new RuntimeException('Versioned CRM draft updates failed.');
}
$rejected = false;
try {
  $store->save(987656, 'Stale write', 'Won', 1);
}
catch (RuntimeException) {
  $rejected = true;
}
$sourceTitle = $db->query('SELECT title FROM office_crm_legacy_opportunity WHERE legacy_node_id=987656')->fetchColumn();
if (!$rejected || $sourceTitle !== 'Original title' || $store->get(987656)['title'] !== 'Next title') {
  throw new RuntimeException('Stale draft write was accepted or legacy source mutated.');
}
$db->exec('DELETE FROM office_crm_opportunity_draft WHERE legacy_opportunity_id=987656');
$db->exec('DELETE FROM office_crm_legacy_opportunity WHERE legacy_node_id=987656');
echo "Versioned independent CRM draft checks passed.\n";
