<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\Mail\Domain\GatewayRequestSignature;
use Brebo\MailGateway\Contract\DkimKeyGeneratorInterface;
use Brebo\MailGateway\Contract\GatewayProvisioningRepositoryInterface;
use Brebo\MailGateway\Contract\MailStackAdapterInterface;
use Brebo\MailGateway\Domain\GatewayRequest;
use Brebo\MailGateway\Security\GatewayRequestVerifier;
use Brebo\MailGateway\Service\GatewayApiService;
use Brebo\MailGateway\Service\GatewayRequestRouter;

final class SmokeRepository implements GatewayProvisioningRepositoryInterface {
  public function provisionDomain(array $payload): string { return 'domain:test'; }
  public function provisionMailbox(array $payload): string { return 'mailbox:test'; }
  public function provisionAlias(string $aliasAddress, string $targetAddress): string { return 'alias:test'; }
}
final class SmokeMailStack implements MailStackAdapterInterface {
  public array $domains = [];
  public array $mailboxes = [];
  public array $aliases = [];
  public function applyDomain(array $domain): void { $this->domains[] = $domain; }
  public function applyMailbox(array $mailbox): void { $this->mailboxes[] = $mailbox; }
  public function applyAlias(string $aliasAddress, string $targetAddress): void { $this->aliases[$aliasAddress] = $targetAddress; }
  public function health(): array { return ['available' => TRUE, 'message' => 'ok']; }
}
final class SmokeDkim implements DkimKeyGeneratorInterface {
  public function generate(string $domain): array {
    return ['selector' => 'brebo1', 'public_key' => 'PUBLIC', 'private_key_reference' => 'secret://dkim/test'];
  }
}

$keyId = 'office';
$secret = 'test-secret';
$now = 1700000000;
$body = '{"domain":"example.nl"}';
$signature = GatewayRequestSignature::sign($keyId, $secret, $now, 'POST', '/v1/domains', $body);
$request = new GatewayRequest('POST', '/v1/domains', $body, $signature->headers());

$router = new GatewayRequestRouter(
  new GatewayApiService(new SmokeRepository(), new SmokeDkim(), new SmokeMailStack()),
  new GatewayRequestVerifier($keyId, $secret),
);
$response = $router->dispatch($request, $now);

if ($response['status'] !== 200 || ($response['body']['reference'] ?? '') !== 'domain:test') {
  throw new RuntimeException('Signed domain provisioning request failed.');
}
if (($response['body']['dkim']['selector'] ?? '') !== 'brebo1') {
  throw new RuntimeException('DKIM descriptor missing from domain provisioning.');
}

$bad = $router->dispatch(new GatewayRequest('GET', '/v1/health', '', []), $now);
if ($bad['status'] !== 401) {
  throw new RuntimeException('Unsigned request must be rejected.');
}

$srcRoot = dirname(__DIR__) . '/src';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcRoot));
foreach ($iterator as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'php') {
    continue;
  }
  $source = file_get_contents($file->getPathname());
  if ($source === false || str_contains($source, 'Drupal\\')) {
    throw new RuntimeException('Framework dependency detected in ' . $file->getPathname());
  }
}

echo "BREBO_MAIL_GATEWAY_SMOKE=PASS\n";
