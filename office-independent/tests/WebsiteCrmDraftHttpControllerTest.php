<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmDraftAccessGate;
use Brebo\Office\Intake\WebsiteCrmDraftApplicationService;
use Brebo\Office\Intake\WebsiteCrmDraftCsrf;
use Brebo\Office\Intake\WebsiteCrmDraftFormRenderer;
use Brebo\Office\Intake\WebsiteCrmDraftHttpController;
use Brebo\Office\Intake\WebsiteCrmDraftSubmissionHandler;
use Brebo\Office\Intake\WebsiteCrmDraftSubmissionValidator;
use Brebo\Office\Intake\WebsiteCrmLegacyReadRepository;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Intake\WebsiteCrmOperatorSessionProviderInterface;
use Brebo\Office\Intake\WebsiteCrmOpportunityDraftStore;

foreach (['WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteCrmDraftAccessGate','WebsiteCrmDraftCsrf','WebsiteCrmDraftSubmissionValidator','WebsiteCrmOpportunityDraftStore','WebsiteCrmDraftSubmissionHandler','WebsiteCrmDraftApplicationService','WebsiteCrmDraftFormRenderer','WebsiteCrmLegacyReadRepository','WebsiteCrmDraftHttpController'] as $name) {
  require_once __DIR__ . '/../src/Intake/' . $name . '.php';
}
$db = new PDO(getenv('OFFICE_INTAKE_TEST_DSN'), getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec(WebsiteCrmOpportunityDraftStore::schema());
$db->exec("INSERT INTO office_crm_legacy_opportunity (legacy_node_id,title,stage,owner_uid,website_request_id,source_json) VALUES (987659,'Legacy','Lead',1,NULL,'{}')");
$csrf = new WebsiteCrmDraftCsrf(str_repeat('s', 64));
$app = new WebsiteCrmDraftApplicationService($csrf, new WebsiteCrmDraftSubmissionHandler(new WebsiteCrmDraftSubmissionValidator(), $csrf, new WebsiteCrmOpportunityDraftStore($db)));
$make = static function (?WebsiteCrmOperatorContext $identity) use ($app, $db): WebsiteCrmDraftHttpController {
  $provider = new class($identity) implements WebsiteCrmOperatorSessionProviderInterface {
    public function __construct(private readonly ?WebsiteCrmOperatorContext $identity) {}
    public function currentOperator(): ?WebsiteCrmOperatorContext { return $this->identity; }
  };
  return new WebsiteCrmDraftHttpController(new WebsiteCrmDraftAccessGate($provider), $app, new WebsiteCrmDraftFormRenderer(), new WebsiteCrmLegacyReadRepository($db), new WebsiteCrmOpportunityDraftStore($db));
};
$editor = new WebsiteCrmOperatorContext('editor', 'session-e', true);
$viewer = new WebsiteCrmOperatorContext('viewer', 'session-v', false);
$post = ['id'=>'987659','version'=>'0','title'=>'Draft','stage'=>'Qualified','csrf_token'=>$app->formToken($editor,987659,100000)];
if ($make(null)->form(987659,100001)['status'] !== 403
  || $make($viewer)->submit($post,100001)['status'] !== 403
  || $make($editor)->form(987659,100001)['status'] !== 200
  || $make($editor)->submit($post,100001)['status'] !== 200
  || $make($editor)->submit($post,100001)['status'] !== 409) {
  throw new RuntimeException('CRM HTTP draft authorization, form, write or conflict checks failed.');
}
if ($db->query('SELECT title FROM office_crm_legacy_opportunity WHERE legacy_node_id=987659')->fetchColumn() !== 'Legacy') {
  throw new RuntimeException('CRM HTTP draft mutated legacy source.');
}
$db->exec('DELETE FROM office_crm_opportunity_draft WHERE legacy_opportunity_id=987659');
$db->exec('DELETE FROM office_crm_legacy_opportunity WHERE legacy_node_id=987659');
echo "CRM draft HTTP controller checks passed.\n";
