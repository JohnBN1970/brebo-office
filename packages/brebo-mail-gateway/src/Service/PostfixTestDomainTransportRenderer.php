<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

final class PostfixTestDomainTransportRenderer {

  /**
   * @param array<int,array<string,mixed>> $domains
   * @return array{transport_map:string,master_cf_fragment:string}
   */
  public function render(array $domains): array {
    $lines = [];
    foreach ($domains as $domain) {
      $name = mb_strtolower(trim((string) ($domain['domain'] ?? '')));
      $isTest = filter_var($domain['test_domain'] ?? FALSE, FILTER_VALIDATE_BOOL);
      $allowed = filter_var($domain['cutover_allowed'] ?? FALSE, FILTER_VALIDATE_BOOL);

      if ($name === '' || !$isTest || !$allowed) {
        continue;
      }

      $lines[] = $name . ' brebo_inbound:';
    }

    sort($lines);

    $master = <<<'CONF'
brebo_inbound unix  -       n       n       -       -       pipe
  flags=Rq user=brebomail argv=/opt/brebo-office/packages/brebo-mail-gateway/bin/brebo-mail-deliver ${recipient}
CONF;

    $main = "transport_maps = hash:/etc/brebo-mail-stack/generated/transport_map\n";

    return [
      'transport_map' => implode("\n", $lines) . ($lines !== [] ? "\n" : ''),
      'master_cf_fragment' => $master . "\n",
      'main_cf_fragment' => $main,
    ];
  }
}
