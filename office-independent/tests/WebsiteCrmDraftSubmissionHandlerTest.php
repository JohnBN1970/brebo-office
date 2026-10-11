<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmDraftSubmissionHandler;
use Brebo\Office\Intake\WebsiteCrmDraftSubmissionValidator;
use Brebo\Office\Intake\WebsiteCrmDraftCsrf;
use Brebo\Office\Intake\WebsiteCrmOpportunityDraftStore;

foreach (['WebsiteCrmDraftSubmissionHandler', 'WebsiteCrmDraftSubmissionValidator', 'WebsiteCrmDraftCsrf', 'WebsiteCrmOpportunityDraftStore'] as $name) {
  require_once __DIR__ . '/../src/Intake/' . $name . '.php';
}
$db = new PDO(getenv('OFFICE_INTAKE_TEST_DSN'), getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE IF NOT EXISTS office_crm_opportunity_draft (legacy_opportunity_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,title VARCHAR(255) NOT NULL,stage VARCHAR(80) NOT NULL,version INT UNSIGNED NOT NULL DEFAULT 1,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO office_crm_legacy_opportunity (legacy_node_id,title,stage,owner_uid,website_request_id,source_json) VALUES (987657,'Source','Lead',1,NULL,'{}')");
$csrf = new WebsiteCrmDraftCsrf(str_repeat('k', 64));
$handler = new WebsiteCrmDraftSubmissionHandler(new WebsiteCrmDraftSubmissionValidator(), $csrf, new WebsiteCrmOpportunityDraftStore($db));
$post = ['id' => '987657', 'version' => '0', 'title' => 'Office draft', 'stage' => 'Qualified', 'csrf_token' => $csrf->issue('operator-1', 987657, 100000)];
$denied = false;
try {
  $handler->submit($post, 'operator-2', 100001);
}
catch (DomainException) {
  $denied = true;
}
if (!$denied || $handler->submit($post, 'operator-1', 100001) !== 1) {
  throw new RuntimeException('CRM draft submission authorization or write failed.');
}
$stale = false;
try {
  $handler->submit($post, 'operator-1', 100001);
}
catch (RuntimeException) {
  $stale = true;
}
if (!$stale || $db->query('SELECT title FROM office_crm_legacy_opportunity WHERE legacy_node_id=987657')->fetchColumn() !== 'Source') {
  throw new RuntimeException('CRM draft concurrency protection or source isolation failed.');
}
$db->exec('DELETE FROM office_crm_opportunity_draft WHERE legacy_opportunity_id=987657');
$db->exec('DELETE FROM office_crm_legacy_opportunity WHERE legacy_node_id=987657');
echo "CRM draft submission handler checks passed.\n";
