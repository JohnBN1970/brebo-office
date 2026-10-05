<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\MailGateway\Contract\StdinCommandRunnerInterface;
use Brebo\MailGateway\Infrastructure\MaildirMailboxStore;
use Brebo\MailGateway\Infrastructure\PostfixSendmailTransport;
use RuntimeException;

final class FakeStdinRunner implements StdinCommandRunnerInterface {
  public array $calls = [];
  public function runWithInput(string $command, array $arguments, string $input): array {
    $this->calls[] = [$command, $arguments, $input];
    return ['exit_code' => 0, 'stdout' => '', 'stderr' => ''];
  }
}

$runner = new FakeStdinRunner();
$disabled = new PostfixSendmailTransport($runner, FALSE);
try {
  $disabled->send(['from' => 'john@mail-test.example.nl', 'to' => 'outside@example.com']);
  throw new RuntimeException('Disabled SMTP adapter must reject sends.');
}
catch (RuntimeException $e) {
  if ($e->getMessage() !== 'Live SMTP transport is niet geactiveerd.') {
    throw $e;
  }
}

$enabled = new PostfixSendmailTransport($runner, TRUE);
$enabled->send([
  'from' => 'john@mail-test.example.nl',
  'to' => 'outside@example.com',
  'subject' => 'Test',
  'text' => 'Body',
]);
if (($runner->calls[0][0] ?? '') !== 'sendmail' || !str_contains((string) ($runner->calls[0][2] ?? ''), 'Subject: Test')) {
  throw new RuntimeException('Postfix sendmail stdin path failed.');
}

$root = sys_get_temp_dir() . '/brebo-maildir-' . bin2hex(random_bytes(4));
$store = new MaildirMailboxStore($root, TRUE);
$ref = $store->append('john@mail-test.example.nl', 'INBOX', [
  'from' => 'outside@example.com',
  'to' => 'john@mail-test.example.nl',
  'subject' => 'Inbound',
  'text' => 'Hello',
]);
if (!str_starts_with($ref, 'maildir:')) {
  throw new RuntimeException('Maildir append failed.');
}
$recent = $store->recent('john@mail-test.example.nl', 'INBOX');
if (count($recent) !== 1 || !str_contains((string) ($recent[0]['raw'] ?? ''), 'Subject: Inbound')) {
  throw new RuntimeException('Maildir recent read failed.');
}

echo "BREBO_MAIL_RUNTIME_ADAPTERS=PASS\n";
