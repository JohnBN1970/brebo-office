<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmDraftApplicationService;
use Brebo\Office\Intake\WebsiteCrmDraftCsrf;
use Brebo\Office\Intake\WebsiteCrmDraftSubmissionHandler;
use Brebo\Office\Intake\WebsiteCrmDraftSubmissionValidator;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Intake\WebsiteCrmOpportunityDraftStore;

foreach (['WebsiteCrmDraftApplicationService', 'WebsiteCrmDraftCsrf', 'WebsiteCrmDraftSubmissionHandler', 'WebsiteCrmDraftSubmissionValidator', 'WebsiteCrmOperatorContext', 'WebsiteCrmOpportunityDraftStore'] as $name) {
  require_once __DIR__ . '/../src/Intake/' . $name . '.php';
}
$db = new PDO(getenv('OFFICE_INTAKE_TEST_DSN'), getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec(WebsiteCrmOpportunityDraftStore::schema());
$db->exec("INSERT INTO office_crm_legacy_opportunity (legacy_node_id,title,stage,owner_uid,website_request_id,source_json) VALUES (987658,'Legacy source','Lead',1,NULL,'{}')");
$csrf = new WebsiteCrmDraftCsrf(str_repeat('a', 64));
$app = new WebsiteCrmDraftApplicationService($csrf, new WebsiteCrmDraftSubmissionHandler(new WebsiteCrmDraftSubmissionValidator(), $csrf, new WebsiteCrmOpportunityDraftStore($db)));
$viewer = new WebsiteCrmOperatorContext('viewer', 'session-v', false);
$editor = new WebsiteCrmOperatorContext('editor', 'session-e', true);
$denied = false;
try {
  $app->formToken($viewer, 987658, 100000);
}
catch (DomainException) {
  $denied = true;
}
if (!$denied) {
  throw new RuntimeException('Viewer received CRM edit token.');
}
$post = ['id' => '987658', 'version' => '0', 'title' => 'Draft', 'stage' => 'Qualified', 'csrf_token' => $app->formToken($editor, 987658, 100000)];
$denied = false;
try {
  $app->save($viewer, $post, 100001);
}
catch (DomainException) {
  $denied = true;
}
if (!$denied || $app->save($editor, $post, 100001) !== 1) {
  throw new RuntimeException('CRM operator authorization boundary failed.');
}
if ($db->query('SELECT title FROM office_crm_legacy_opportunity WHERE legacy_node_id=987658')->fetchColumn() !== 'Legacy source') {
  throw new RuntimeException('CRM draft application modified legacy source.');
}
$db->exec('DELETE FROM office_crm_opportunity_draft WHERE legacy_opportunity_id=987658');
$db->exec('DELETE FROM office_crm_legacy_opportunity WHERE legacy_node_id=987658');
echo "CRM operator-bound draft application checks passed.\n";
