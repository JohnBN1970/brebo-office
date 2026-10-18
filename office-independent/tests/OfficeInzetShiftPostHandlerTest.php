<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftCsrf.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftSubmission.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftPostHandler.php';

use Brebo\Office\Inzet\OfficeInzetShiftSubmission;
use Brebo\Office\Inzet\OfficeInzetShiftPostHandler;
use Brebo\Office\Inzet\OfficeInzetShiftCsrf;

$submission = new class extends OfficeInzetShiftSubmission {
  public int $calls = 0;
  public function __construct() {}
  public function createForOperator(string $operatorId, array $form): int {
    $this->calls++;
    if ($operatorId !== '7') throw new RuntimeException('Unexpected operator');
    return 51;
  }
};
$handler = new OfficeInzetShiftPostHandler($submission);
$token = OfficeInzetShiftCsrf::issue();
foreach ([['GET','7',$token],['POST','',$token],['POST','7',null],['POST','7',str_repeat('0',64)]] as [$method,$operator,$submitted]) {
  $rejected = false;
  try { $handler->handle($method,$operator,['csrf_token'=>$submitted],$token); }
  catch (RuntimeException $e) { $rejected = true; }
  if (!$rejected) throw new RuntimeException('Unsafe shift request accepted');
}
if ($submission->calls !== 0) throw new RuntimeException('Rejected request reached persistence');
if ($handler->handle('POST','7',['csrf_token'=>$token],$token) !== 51 || $submission->calls !== 1) {
  throw new RuntimeException('Authorized request not forwarded');
}
echo "Office shift POST boundary test OK\n";
