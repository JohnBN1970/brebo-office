<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\Mail\Domain\GatewayRequestSignature;
use Brebo\MailGateway\Contract\DkimKeyGeneratorInterface;
use Brebo\MailGateway\Contract\GatewayProvisioningRepositoryInterface;
use Brebo\MailGateway\Contract\MailStackAdapterInterface;
use Brebo\MailGateway\Contract\MailStackCommandRunnerInterface;
use Brebo\MailGateway\Domain\GatewayRequest;
use Brebo\MailGateway\Domain\MailStackProjection;
use Brebo\MailGateway\Security\GatewayRequestVerifier;
use Brebo\MailGateway\Service\DovecotProjectionRenderer;
use Brebo\MailGateway\Service\MailStackConfigBundleRenderer;
use Brebo\MailGateway\Service\PostfixProjectionRenderer;
use Brebo\MailGateway\Service\RspamdProjectionRenderer;
use Brebo\MailGateway\Service\MailStackReloadGate;
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
final class SmokeRunner implements MailStackCommandRunnerInterface {
  public array $calls = [];
  public function run(string $command, array $arguments = []): array {
    $this->calls[] = [$command, $arguments];
    return ['exit_code' => 0, 'stdout' => 'ok', 'stderr' => ''];
  }
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

$projection = new MailStackProjection(
  ['example.nl'],
  ['info@example.nl'],
  ['alias@example.nl' => 'info@example.nl'],
  ['example.nl' => ['selector' => 'brebo1', 'private_key_reference' => 'file:///keys/example.pem']],
);
$bundle = (new MailStackConfigBundleRenderer(
  new PostfixProjectionRenderer(),
  new DovecotProjectionRenderer(),
  new RspamdProjectionRenderer(),
))->render($projection);

if ($bundle['virtual_domains'] !== "example.nl OK\n") {
  throw new RuntimeException('Postfix domain projection failed.');
}
if ($bundle['virtual_mailboxes'] !== "info@example.nl 1\n") {
  throw new RuntimeException('Postfix mailbox projection failed.');
}
if ($bundle['virtual_aliases'] !== "alias@example.nl info@example.nl\n") {
  throw new RuntimeException('Postfix alias projection failed.');
}
if ($bundle['users'] !== "info@example.nl:*::::::\n") {
  throw new RuntimeException('Dovecot projection failed.');
}
if ($bundle['dkim_map'] !== "example.nl brebo1 file:///keys/example.pem\n") {
  throw new RuntimeException('Rspamd DKIM projection failed.');
}

$runner = new SmokeRunner();
$gate = new MailStackReloadGate($runner, FALSE);
$checks = $gate->validate();
if (count($checks) !== 3) {
  throw new RuntimeException('Mailstack validation gate failed.');
}
try {
  $gate->reload();
  throw new RuntimeException('Reload must stay disabled by default.');
}
catch (RuntimeException $e) {
  if ($e->getMessage() !== 'Mailstack reload is niet geactiveerd.') {
    throw $e;
  }
}


$db = new PDO('sqlite::memory:');
$repo = new \Brebo\MailGateway\Infrastructure\SqliteGatewayProvisioningRepository($db);
$stack = new SmokeMailStack();

$passwordRouter = new GatewayRequestRouter(
  new GatewayApiService($repo, new SmokeDkim(), $stack),
  new GatewayRequestVerifier($keyId, $secret),
);

$repo->provisionDomain(['domain'=>'testmail.brebobv.nl']);
$address = 'controle@testmail.brebobv.nl';
$reference = $repo->provisionMailbox(['address'=>$address]);
$password = bin2hex(random_bytes(3));

$submit = static function (
  array $payload, bool $signed = true
) use ($passwordRouter, $keyId, $secret, $now): array {
  $path = '/v1/mailboxes/password';
  $body = json_encode($payload, JSON_THROW_ON_ERROR);
  $headers = $signed
    ? GatewayRequestSignature::sign(
        $keyId, $secret, $now, 'POST', $path, $body
      )->headers()
    : [];

  return $passwordRouter->dispatch(
    new GatewayRequest('POST', $path, $body, $headers),
    $now
  );
};

$payload = [
  'address'=>$address,
  'reference'=>$reference,
  'password'=>$password,
];

$check = static function (bool $ok, string $name): void {
  if (!$ok) {
    throw new RuntimeException($name . ' failed');
  }
  echo $name . "=PASS\n";
};

$response = $submit($payload, false);
$check($response['status'] === 401, 'PASSWORD_UNSIGNED_BLOCKED');

$response = $submit($payload);
$check(
  $response['status'] === 200
  && ($response['body']['password_stored'] ?? null) === true
  && ($response['body']['activation_changed'] ?? null) === false
  && ($response['body']['reference'] ?? null) === $reference
  && ($response['body']['address'] ?? null) === $address
  && !str_contains(json_encode($response), $password),
  'PASSWORD_SIGNED_REQUEST'
);

$hash = $db->query(
  'SELECT password_hash FROM mailbox_credential'
)->fetchColumn();

$check(
  str_starts_with($hash, '{ARGON2ID}')
  && password_verify($password, substr($hash, 10)),
  'PASSWORD_HASH_VERIFIED'
);

foreach ([
  'WRONG_REFERENCE'=>['reference'=>'invalid'],
  'PRODUCTION_DOMAIN'=>['address'=>'info@brebobv.nl'],
  'UNKNOWN_MAILBOX'=>['address'=>'unknown@testmail.brebobv.nl'],
  'SHORT_PASSWORD'=>['password'=>'short'],
] as $name=>$override) {
  $response = $submit(array_replace($payload, $override));
  $check($response['status'] === 422, 'PASSWORD_'.$name);
}

$check(
  $db->query('SELECT password_hash FROM mailbox_credential')->fetchColumn() === $hash,
  'PASSWORD_REJECTED_REQUESTS_NO_CHANGE'
);

$newPassword = bin2hex(random_bytes(24));
$response = $submit(array_replace($payload, ['password'=>$newPassword]));
$newHash = $db->query(
  'SELECT password_hash FROM mailbox_credential'
)->fetchColumn();

$check(
  $response['status'] === 200
  && password_verify($newPassword, substr($newHash, 10))
  && !password_verify($password, substr($newHash, 10)),
  'PASSWORD_RESET'
);

$check(
  $stack->domains === []
  && $stack->mailboxes === []
  && $stack->aliases === [],
  'PASSWORD_NO_MAILSTACK_CHANGE'
);


$statusRequest = static function (
  array $payload, bool $signed = true
) use ($passwordRouter, $keyId, $secret, $now): array {
  $path = '/v1/mailboxes/password-status';
  $body = json_encode($payload, JSON_THROW_ON_ERROR);
  $headers = $signed
    ? GatewayRequestSignature::sign(
        $keyId, $secret, $now, 'POST', $path, $body
      )->headers()
    : [];

  return $passwordRouter->dispatch(
    new GatewayRequest('POST', $path, $body, $headers),
    $now
  );
};

$statusPayload = [
  'address'=>$address,
  'reference'=>$reference,
];

$response = $statusRequest($statusPayload);

$check(
  $response['status'] === 200
  && $response['body']['password_set'] === true
  && is_int($response['body']['changed_at'])
  && $response['body']['changed_at'] > 0
  && count($response['body']) === 4
  && !str_contains(json_encode($response), $newHash),
  'PASSWORD_STATUS_STORED'
);

$emptyAddress = 'empty@testmail.brebobv.nl';
$emptyRef = $repo->provisionMailbox(['address'=>$emptyAddress]);

$response = $statusRequest([
  'address'=>$emptyAddress,
  'reference'=>$emptyRef,
]);

$check(
  $response['status'] === 200
  && $response['body']['password_set'] === false
  && $response['body']['changed_at'] === null,
  'PASSWORD_STATUS_NOT_SET'
);

$check(
  $statusRequest($statusPayload, false)['status'] === 401,
  'PASSWORD_STATUS_UNSIGNED_BLOCKED'
);

$check(
  $statusRequest(array_replace($statusPayload, [
    'reference'=>'invalid',
  ]))['status'] === 422,
  'PASSWORD_STATUS_WRONG_REFERENCE'
);

$check(
  $statusRequest(array_replace($statusPayload, [
    'address'=>'info@brebobv.nl',
  ]))['status'] === 422,
  'PASSWORD_STATUS_PRODUCTION_BLOCKED'
);

echo "MAIL_PASSWORD_STATUS_TEST=PASS\n";

echo "MAIL_PASSWORD_API_TEST=PASS\n";

echo "BREBO_MAIL_GATEWAY_SMOKE=PASS\n";
