<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Domain\CutoverPlan;
use Brebo\MailGateway\Domain\CutoverStatus;
use RuntimeException;

final class TestDomainScenario {

  public function __construct(
    private readonly GatewayApiService $api,
    private readonly MailStackConfigPublisher $publisher,
    private readonly CutoverGuard $cutoverGuard,
  ) {}

  /** @return array<string,mixed> */
  public function run(string $domain, string $mailboxLocalPart = 'test'): array {
    $domain = mb_strtolower(trim($domain));
    if ($domain === '' || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === FALSE) {
      throw new RuntimeException('Ongeldig testdomein.');
    }

    $domainResult = $this->api->provisionDomain([
      'domain' => $domain,
      'test_domain' => TRUE,
      'cutover_allowed' => TRUE,
    ]);

    $address = $mailboxLocalPart . '@' . $domain;
    $mailboxResult = $this->api->provisionMailbox([
      'address' => $address,
      'label' => 'BREBO Mail test',
      'privacy_type' => 'functional',
    ]);

    $alias = 'alias-' . $mailboxLocalPart . '@' . $domain;
    $aliasResult = $this->api->provisionAlias($alias, $address);

    $bundle = $this->publisher->publish();

    return [
      'domain' => $domain,
      'domain_reference' => (string) ($domainResult['reference'] ?? ''),
      'dkim' => (array) ($domainResult['dkim'] ?? []),
      'mailbox' => $address,
      'mailbox_reference' => (string) ($mailboxResult['reference'] ?? ''),
      'alias' => $alias,
      'alias_reference' => (string) ($aliasResult['reference'] ?? ''),
      'config_files' => array_keys($bundle),
      'cutover_plan' => new CutoverPlan($domain, TRUE, TRUE, CutoverStatus::Draft),
    ];
  }

  /** @param array<string,string> $dnsChecks */
  public function validateForActivation(CutoverPlan $plan, array $dnsChecks, bool $mailstackValid): CutoverPlan {
    return $this->cutoverGuard->validate(
      $plan,
      $this->api->health(),
      $dnsChecks,
      $mailstackValid,
    );
  }
}
