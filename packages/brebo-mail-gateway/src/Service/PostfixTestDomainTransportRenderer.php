<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

final class PostfixTestDomainTransportRenderer {

  /** @param string[] $trustedTestDomains */
  public function __construct(private readonly array $trustedTestDomains) {}

  /**
   * @param array<int,array<string,mixed>> $domains
   * @return array{transport_map:string,master_cf_fragment:string,main_cf_fragment:string}
   */
  public function render(array $domains): array {
    $trusted = array_values(array_unique(array_filter(array_map(
      static fn(string $domain): string => mb_strtolower(trim($domain)),
      $this->trustedTestDomains,
    ))));

    $lines = [];
    foreach ($domains as $domain) {
      $name = mb_strtolower(trim((string) ($domain['domain'] ?? '')));
      $isTest = filter_var($domain['test_domain'] ?? FALSE, FILTER_VALIDATE_BOOL);
      $allowed = filter_var($domain['cutover_allowed'] ?? FALSE, FILTER_VALIDATE_BOOL);

      if ($name === '' || !$isTest || !$allowed || !in_array($name, $trusted, TRUE)) {
        continue;
      }

      $lines[] = $name . ' brebo_inbound:';
    }

    sort($lines);

    $master = <<<'CONF'
brebo_inbound unix  -       n       n       -       -       pipe
  flags=Rq user=brebomail argv=/opt/brebo-office/packages/brebo-mail-gateway/bin/brebo-mail-deliver ${recipient}
CONF;

    return [
      'transport_map' => implode("\n", $lines) . ($lines !== [] ? "\n" : ''),
      'master_cf_fragment' => $master . "\n",
      'main_cf_fragment' => "transport_maps = hash:/etc/brebo-mail-stack/generated/transport_map\n",
    ];
  }
}
