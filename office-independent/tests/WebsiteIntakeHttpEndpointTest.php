<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteIntakeHandler;
use Brebo\Office\Intake\WebsiteIntakeHttpEndpoint;
use Brebo\Office\Intake\WebsiteIntakeSignatureVerifier;
use Brebo\Office\Intake\WebsiteIntakeValidator;
use Brebo\Office\Intake\WebsiteLeadRepositoryInterface;

foreach (['WebsiteLeadRepositoryInterface', 'WebsiteIntakeValidator', 'WebsiteIntakeHandler', 'WebsiteIntakeSignatureVerifier', 'WebsiteIntakeHttpEndpoint'] as $class) {
  require_once __DIR__ . '/../src/Intake/' . $class . '.php';
}
$repo = new class implements WebsiteLeadRepositoryInterface {
  public int $calls = 0;
  public function acceptWebsiteLead(string $requestId, string $source, array $payload): array {
    $this->calls++;
    return ['opportunity_id' => 77, 'duplicate' => false, 'state' => 'review_required'];
  }
};
$endpoint = new WebsiteIntakeHttpEndpoint(
  new WebsiteIntakeSignatureVerifier('test-secret'),
  new WebsiteIntakeHandler(new WebsiteIntakeValidator(), $repo),
);
$id = 'aaaaaaaa-1111-4111-8111-111111111111';
$body = json_encode([
  'request_id' => $id, 'source' => 'website', 'schema_version' => '1',
  'observed' => ['building' => [], 'rooms' => [['id' => 'r1']], 'frames' => [['id' => 'f1']]],
  'detected' => [], 'calculated' => [], 'selected' => [],
], JSON_THROW_ON_ERROR);
$now = 1791500000;
$path = WebsiteIntakeHttpEndpoint::PATH;
$canonical = implode("\n", ['POST', $path, hash('sha256', $body), (string) $now, $id]);
$headers = ['X-BREBO-Timestamp' => (string) $now, 'X-BREBO-Request-Id' => $id, 'X-BREBO-Signature' => 'v1=' . hash_hmac('sha256', $canonical, 'test-secret')];
$ok = $endpoint->dispatch('POST', $path, $body, $headers, $now);
if ($ok['status'] !== 202 || $ok['body']['opportunity_id'] !== 77 || $repo->calls !== 1) {
  throw new RuntimeException('Signed HTTP intake was not accepted.');
}
$rejected = $endpoint->dispatch('POST', $path, $body . ' ', $headers, $now);
if ($rejected['status'] !== 401 || $repo->calls !== 1) {
  throw new RuntimeException('Tampered HTTP intake reached CRM.');
}
$wrongMethod = $endpoint->dispatch('GET', $path, $body, $headers, $now);
if ($wrongMethod['status'] !== 405) {
  throw new RuntimeException('Non-POST method accepted.');
}
$missingSignature = $endpoint->dispatch('POST', $path, $body, [], $now);
if ($missingSignature['status'] !== 401 || $repo->calls !== 1) {
  throw new RuntimeException('Unsigned HTTP intake reached CRM.');
}
$expired = $endpoint->dispatch('POST', $path, $body, $headers, $now + 301);
if ($expired['status'] !== 401 || $repo->calls !== 1) {
  throw new RuntimeException('Expired HTTP intake reached CRM.');
}
$badPayload = json_encode([
  'request_id' => $id, 'source' => 'website', 'schema_version' => '1',
  'observed' => ['building' => [], 'rooms' => [], 'frames' => []],
  'detected' => [], 'calculated' => [], 'selected' => [],
], JSON_THROW_ON_ERROR);
$badCanonical = implode("\\n", ['POST', $path, hash('sha256', $badPayload), (string) $now, $id]);
$badHeaders = array_replace($headers, [
  'X-BREBO-Signature' => 'v1=' . hash_hmac('sha256', $badCanonical, 'test-secret'),
]);
$invalid = $endpoint->dispatch('POST', $path, $badPayload, $badHeaders, $now);
if ($invalid['status'] !== 422 || $repo->calls !== 1) {
  throw new RuntimeException('Invalid but signed intake reached CRM.');
}
echo "Independent HTTP endpoint checks passed.\n";
