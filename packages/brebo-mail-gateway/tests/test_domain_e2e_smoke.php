<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\MailGateway\Contract\DkimKeyGeneratorInterface;
use Brebo\MailGateway\Contract\GatewayProvisioningRepositoryInterface;
use Brebo\MailGateway\Contract\GatewayStateReaderInterface;
use Brebo\MailGateway\Contract\MailStackAdapterInterface;
use Brebo\MailGateway\Domain\CutoverStatus;
use Brebo\MailGateway\Service\CutoverGuard;
use Brebo\MailGateway\Service\DovecotProjectionRenderer;
use Brebo\MailGateway\Service\GatewayApiService;
use Brebo\MailGateway\Service\MailStackConfigBundleRenderer;
use Brebo\MailGateway\Service\MailStackConfigPublisher;
use Brebo\MailGateway\Service\MailStackProjectionBuilder;
use Brebo\MailGateway\Service\PostfixProjectionRenderer;
use Brebo\MailGateway\Service\RspamdProjectionRenderer;
use Brebo\MailGateway\Service\TestDomainScenario;

final class E2EState implements GatewayProvisioningRepositoryInterface, GatewayStateReaderInterface {
  public array $domains = [];
  public array $mailboxes = [];
  public array $aliases = [];

  public function provisionDomain(array $payload): string {
    $this->domains[] = $payload;
    return 'domain:test';
  }
  public function provisionMailbox(array $payload): string {
    $this->mailboxes[] = $payload;
    return 'mailbox:test';
  }
  public function provisionAlias(string $aliasAddress, string $targetAddress): string {
    $this->aliases[] = ['address' => $aliasAddress, 'target_address' => $targetAddress];
    return 'alias:test';
  }
  public function domains(): array { return $this->domains; }
  public function mailboxes(): array { return $this->mailboxes; }
  public function aliases(): array { return $this->aliases; }
}
final class E2EDkim implements DkimKeyGeneratorInterface {
  public function generate(string $domain): array {
    return ['selector' => 'brebo1', 'public_key' => 'PUBLIC', 'private_key_reference' => 'file:///tmp/test.pem'];
  }
}
final class E2EStack implements MailStackAdapterInterface {
  public function applyDomain(array $domain): void {}
  public function applyMailbox(array $mailbox): void {}
  public function applyAlias(string $aliasAddress, string $targetAddress): void {}
  public function health(): array { return ['available' => TRUE, 'message' => 'ok']; }
}

$state = new E2EState();
$output = sys_get_temp_dir() . '/brebo-mail-e2e-' . bin2hex(random_bytes(4));
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
  new GatewayApiService($state, new E2EDkim(), new E2EStack()),
  $publisher,
  new CutoverGuard(),
);

$result = $scenario->run('mail-test.example.nl', 'john');
if (($result['mailbox'] ?? '') !== 'john@mail-test.example.nl') {
  throw new RuntimeException('Test mailbox provisioning failed.');
}
if (($result['alias'] ?? '') !== 'alias-john@mail-test.example.nl') {
  throw new RuntimeException('Test alias provisioning failed.');
}
if (($result['cutover_plan']->status ?? NULL) !== CutoverStatus::Draft) {
  throw new RuntimeException('Test-domain scenario must remain draft before validation.');
}
if (!is_file($output . '/virtual_domains') || !is_file($output . '/virtual_mailboxes')) {
  throw new RuntimeException('Mailstack config was not rendered.');
}

echo "BREBO_MAIL_TEST_DOMAIN_E2E=PASS\n";
