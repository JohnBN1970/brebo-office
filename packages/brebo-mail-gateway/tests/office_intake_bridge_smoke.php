<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\Mail\Contract\MailIntakeSinkInterface;
use Brebo\Mail\Contract\MailboxDirectoryInterface;
use Brebo\Mail\Domain\GatewayRequestSignature;
use Brebo\Mail\Domain\NormalizedMailMessage;
use Brebo\Mail\Service\MailIntakeAdmissionService;
use Brebo\Mail\Service\MailIntakeBridge;
use Brebo\MailGateway\Service\OfficeIntakeRequestFactory;

final class SmokeDirectory implements MailboxDirectoryInterface {
  public function activeAddresses(): array {
    return ['john@mail-test.example.nl'];
  }
}

final class SmokeSink implements MailIntakeSinkInterface {
  public ?NormalizedMailMessage $message = NULL;

  public function ingest(NormalizedMailMessage $message): array {
    $this->message = $message;
    return ['state' => 'created', 'node_id' => 123];
  }
}

$message = new NormalizedMailMessage(
  'gateway:test:1',
  'outside@example.com',
  'john@mail-test.example.nl',
  'Gateway naar Office',
  'Testbericht',
  '<p>Testbericht</p>',
  '2026-10-06T01:00:00Z',
  'thread-test',
);

$factory = new OfficeIntakeRequestFactory('office-callback', 'secret');
$request = $factory->create($message, 1700000000);

if ($request['path'] !== '/mail/api/v1/intake') {
  throw new RuntimeException('Office intake path mismatch.');
}
if (!str_contains($request['body'], '"source_id":"gateway:test:1"')) {
  throw new RuntimeException('Normalized mail payload missing source id.');
}

$expected = GatewayRequestSignature::sign(
  'office-callback',
  'secret',
  1700000000,
  'POST',
  '/mail/api/v1/intake',
  $request['body'],
);
if (($request['headers']['X-Brebo-Signature'] ?? '') !== $expected->signature) {
  throw new RuntimeException('Office intake callback signature mismatch.');
}

$sink = new SmokeSink();
$bridge = new MailIntakeBridge($sink);
$admission = new MailIntakeAdmissionService(new SmokeDirectory(), $bridge);
$result = $admission->ingest($message);
if (($result['node_id'] ?? 0) !== 123 || $sink->message?->subject !== 'Gateway naar Office') {
  throw new RuntimeException('Mail intake bridge failed.');
}

try {
  $admission->ingest(new NormalizedMailMessage(
    'gateway:test:2',
    'outside@example.com',
    'unknown@example.nl',
    'Onbekende mailbox',
    'Moet worden geweigerd',
  ));
  throw new RuntimeException('Unknown Office mailbox recipient must be rejected.');
}
catch (RuntimeException $e) {
  if (!str_contains($e->getMessage(), 'geen actieve Office-mailbox')) {
    throw $e;
  }
}

echo "BREBO_MAIL_OFFICE_INTAKE_BRIDGE=PASS\n";
