<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteIntakeHandler;
use Brebo\Office\Intake\WebsiteIntakeValidator;
use Brebo\Office\Intake\WebsiteLeadRepositoryInterface;

require_once __DIR__ . '/../src/Intake/WebsiteLeadRepositoryInterface.php';
require_once __DIR__ . '/../src/Intake/WebsiteIntakeValidator.php';
require_once __DIR__ . '/../src/Intake/WebsiteIntakeHandler.php';

final class RecordingLeadRepository implements WebsiteLeadRepositoryInterface {
  public int $calls = 0;
  public array $received = [];
  public function acceptWebsiteLead(string $requestId, string $source, array $payload): array {
    $this->calls++;
    $this->received = compact('requestId', 'source', 'payload');
    return ['opportunity_id' => 42, 'duplicate' => false, 'state' => 'review_required'];
  }
}
$repo = new RecordingLeadRepository();
$handler = new WebsiteIntakeHandler(new WebsiteIntakeValidator(), $repo);
$valid = [
  'schema_version' => '1',
  'request_id' => 'aaaaaaaa-1111-4111-8111-111111111111',
  'source' => 'website',
  'observed' => ['building' => [], 'rooms' => [['id' => 'room-1']], 'frames' => [['id' => 'frame-1']]],
  'detected' => [],
  'calculated' => [],
  'selected' => [],
];
try {
  $handler->handle(['request_id' => 'invalid', 'source' => 'website']);
  throw new RuntimeException('Invalid request unexpectedly reached CRM.');
}
catch (\Throwable $e) {
  if ($e->getMessage() === 'Invalid request unexpectedly reached CRM.') {
    throw $e;
  }
}
if ($repo->calls !== 0) {
  throw new RuntimeException('Invalid intake reached CRM repository.');
}
$result = $handler->handle($valid);
if ($repo->calls !== 1 || $repo->received['requestId'] !== $valid['request_id']
  || $result['opportunity_id'] !== 42 || $result['state'] !== 'review_required') {
  throw new RuntimeException('Valid intake did not reach canonical CRM contract.');
}
echo "Handler CRM contract checks passed.\n";
