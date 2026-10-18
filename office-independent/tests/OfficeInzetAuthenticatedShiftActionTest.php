<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorContext.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftCsrf.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSessionCsrf.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftSubmission.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftPostHandler.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetAuthenticatedShiftAction.php';

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Inzet\OfficeInzetShiftSubmission;
use Brebo\Office\Inzet\OfficeInzetShiftPostHandler;
use Brebo\Office\Inzet\OfficeInzetAuthenticatedShiftAction as Action;

$submission = new class extends OfficeInzetShiftSubmission {
  public array $received = [];
  public function __construct() {}
  public function createForOperator(string $operatorId, array $form): int {
    $this->received[] = $operatorId;
    return 77;
  }
};
$action = new Action(new OfficeInzetShiftPostHandler($submission));
$operator = new WebsiteCrmOperatorContext('john.boon',hash('sha256','session-one'),false);
$other = new WebsiteCrmOperatorContext('other.user',hash('sha256','session-two'),false);
$secret = str_repeat('s',40);
$token = Action::formToken($operator,$secret);
if ($action->handle('POST',$operator,['csrf_token'=>$token,'operator_id'=>'forged'],$secret) !== 77) {
  throw new RuntimeException('Valid authenticated shift rejected');
}
if ($submission->received !== ['john.boon']) throw new RuntimeException('Untrusted operator ID used');
foreach ([['GET',$operator,$token],['POST',$other,$token],['POST',$operator,str_repeat('0',64)]] as [$method,$identity,$submitted]) {
  $rejected = false;
  try { $action->handle($method,$identity,['csrf_token'=>$submitted],$secret); }
  catch (RuntimeException $e) { $rejected = true; }
  if (!$rejected) throw new RuntimeException('Unauthorized shift request accepted');
}
if (count($submission->received) !== 1) throw new RuntimeException('Rejected request reached shift writer');
echo "Office authenticated shift action test OK\n";
