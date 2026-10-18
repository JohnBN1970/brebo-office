<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetLocalTime.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftCommand.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftSubmission.php';

use Brebo\Office\Inzet\OfficeInzetShiftCommand;
use Brebo\Office\Inzet\OfficeInzetShiftSubmission;

$command = new class extends OfficeInzetShiftCommand {
  public array $received = [];
  public function __construct() {}
  public function createForOperator(int $operatorId, array $request): int {
    $this->received = ['operator_id'=>$operatorId,'request'=>$request];
    return 42;
  }
};
$submission = new OfficeInzetShiftSubmission($command);
$id = $submission->createForOperator(7,[
  'project_id'=>17,'person_id'=>8,
  'starts_at_local'=>'2026-10-19T09:00',
  'ends_at_local'=>'2026-10-19T17:00',
  'status'=>'confirmed',
]);
if ($id !== 42 || $command->received['operator_id'] !== 7) throw new RuntimeException('Command not called');
$request = $command->received['request'];
if ($request['starts_at'] !== '2026-10-19 07:00:00' || $request['ends_at'] !== '2026-10-19 15:00:00') {
  throw new RuntimeException('Local shift time conversion failed');
}
if (isset($request['status'])) throw new RuntimeException('Untrusted status forwarded');
foreach ([
  ['starts_at_local'=>'2026-03-29T02:30','ends_at_local'=>'2026-03-29T05:00'],
  ['starts_at_local'=>'2026-10-25T02:30','ends_at_local'=>'2026-10-25T05:00'],
  ['starts_at_local'=>'invalid','ends_at_local'=>'2026-10-19T17:00'],
] as $invalid) {
  $rejected = false;
  try { $submission->createForOperator(7,$invalid); } catch (InvalidArgumentException $e) { $rejected = true; }
  if (!$rejected) throw new RuntimeException('Invalid local shift submitted');
}
echo "Office local shift submission test OK\n";
