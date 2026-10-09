<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\MailGateway\Contract\DkimKeyGeneratorInterface;
use Brebo\MailGateway\Contract\GatewayProvisioningRepositoryInterface;
use Brebo\MailGateway\Contract\GatewayStateReaderInterface;
use Brebo\MailGateway\Contract\MailStackAdapterInterface;
use Brebo\MailGateway\Contract\StdinCommandRunnerInterface;
use Brebo\MailGateway\Infrastructure\MaildirMailboxStore;
use Brebo\MailGateway\Infrastructure\PostfixSendmailTransport;
use Brebo\MailGateway\Service\CutoverGuard;
use Brebo\MailGateway\Service\DovecotProjectionRenderer;
use Brebo\MailGateway\Service\GatewayApiService;
use Brebo\MailGateway\Service\MailStackConfigBundleRenderer;
use Brebo\MailGateway\Service\MailStackConfigPublisher;
use Brebo\MailGateway\Service\MailStackProjectionBuilder;
use Brebo\MailGateway\Service\MailTransportService;
use Brebo\MailGateway\Service\PostfixProjectionRenderer;
use Brebo\MailGateway\Service\RspamdProjectionRenderer;
use Brebo\MailGateway\Service\TestDomainScenario;

final class RuntimeE2EState implements GatewayProvisioningRepositoryInterface, GatewayStateReaderInterface {
  public array $domains = [];
  public array $mailboxes = [];
  public array $aliases = [];

  public function provisionDomain(array $payload): string {
    $this->domains[] = $payload;
    return 'domain:e2e';
  }
  public function provisionMailbox(array $payload): string {
    $this->mailboxes[] = $payload;
    return 'mailbox:e2e';
  }
  public function provisionAlias(string $aliasAddress, string $targetAddress): string {
    $this->aliases[] = ['address' => $aliasAddress, 'target_address' => $targetAddress];
    return 'alias:e2e';
  }
  public function domains(): array { return $this->domains; }
  public function mailboxes(): array { return $this->mailboxes; }
  public function aliases(): array { return $this->aliases; }
}

final class RuntimeE2EDkim implements DkimKeyGeneratorInterface {
  public function generate(string $domain): array {
    return [
      'selector' => 'brebo1',
      'public_key' => 'PUBLIC',
      'private_key_reference' => 'file:///tmp/e2e.pem',
    ];
  }
}

final class RuntimeE2EStack implements MailStackAdapterInterface {
  public function applyDomain(array $domain): void {}
  public function applyMailbox(array $mailbox): void {}
  public function applyAlias(string $aliasAddress, string $targetAddress): void {}
  public function health(): array { return ['available' => TRUE, 'message' => 'ok']; }
}

final class RuntimeE2ERunner implements StdinCommandRunnerInterface {
  public array $calls = [];
  public function runWithInput(string $command, array $arguments, string $input): array {
    $this->calls[] = [$command, $arguments, $input];
    return ['exit_code' => 0, 'stdout' => '', 'stderr' => ''];
  }
}

$state = new RuntimeE2EState();
$output = sys_get_temp_dir() . '/brebo-mail-runtime-config-' . bin2hex(random_bytes(4));
$maildir = sys_get_temp_dir() . '/brebo-mail-runtime-maildir-' . bin2hex(random_bytes(4));

$publisher = new MailStackConfigPublisher(
  $state,
  new MailStackProjectionBuilder(),
  new MailStackConfigBundleRenderer(
    new PostfixProjectionRenderer(),
    new DovecotProjectionRenderer(),
    new RspamdProjectionRenderer(),
  ),
  $output,
);

$scenario = new TestDomainScenario(
  new GatewayApiService($state, new RuntimeE2EDkim(), new RuntimeE2EStack()),
  $publisher,
  new CutoverGuard(),
);

$result = $scenario->run('mail-test.example.nl', 'john');
if (($result['mailbox'] ?? '') !== 'john@mail-test.example.nl') {
  throw new RuntimeException('Provisioned mailbox mismatch.');
}

$runner = new RuntimeE2ERunner();
$store = new MaildirMailboxStore($maildir, TRUE);
$transport = new MailTransportService(
  new PostfixSendmailTransport($runner, TRUE),
  $store,
);

$outRef = $transport->send([
  'from' => 'john@mail-test.example.nl',
  'to' => 'outside@example.com',
  'subject' => 'BREBO runtime E2E',
  'text' => 'Outbound test',
]);
if (!str_starts_with($outRef, 'postfix:')) {
  throw new RuntimeException('Outbound Postfix transport failed.');
}
if (count($runner->calls) !== 1 || !str_contains((string) $runner->calls[0][2], 'Subject: BREBO runtime E2E')) {
  throw new RuntimeException('Outbound message did not reach Postfix stdin path.');
}

$inRef = $transport->receive('john@mail-test.example.nl', [
  'from' => 'outside@example.com',
  'to' => 'john@mail-test.example.nl',
  'subject' => 'Inbound runtime E2E',
  'text' => 'Inbound test',
]);
if (!str_starts_with($inRef, 'maildir:')) {
  throw new RuntimeException('Inbound Maildir write failed.');
}

$inbox = $store->recent('john@mail-test.example.nl', 'INBOX');
$sent = $store->recent('john@mail-test.example.nl', 'Sent');
if (count($inbox) !== 1 || !str_contains((string) $inbox[0]['raw'], 'Subject: Inbound runtime E2E')) {
  throw new RuntimeException('Inbox roundtrip failed.');
}
if (count($sent) !== 1 || !str_contains((string) $sent[0]['raw'], 'Subject: BREBO runtime E2E')) {
  throw new RuntimeException('Sent roundtrip failed.');
}

foreach (['virtual_domains', 'virtual_mailboxes', 'virtual_aliases', 'users', 'dkim_map'] as $name) {
  if (!is_file($output . '/' . $name)) {
    throw new RuntimeException('Expected rendered mailstack file missing: ' . $name);
  }
}

$dkimMap = (string) file_get_contents($output . '/dkim_map');
if (
  !str_contains($dkimMap, 'mail-test.example.nl')
  || !str_contains($dkimMap, 'brebo1')
  || !str_contains($dkimMap, 'file:///tmp/e2e.pem')
) {
  throw new RuntimeException('Rspamd DKIM projection is incomplete.');
}

echo "BREBO_MAIL_RUNTIME_E2E=PASS\n";
