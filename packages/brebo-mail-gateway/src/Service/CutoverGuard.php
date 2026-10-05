<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Service;

use Brebo\MailGateway\Domain\CutoverPlan;
use Brebo\MailGateway\Domain\CutoverStatus;
use RuntimeException;

final class CutoverGuard {

  public function validate(CutoverPlan $plan, array $gatewayHealth, array $dnsChecks, bool $mailstackValid): CutoverPlan {
    if (!(bool) ($gatewayHealth['available'] ?? FALSE)) {
      throw new RuntimeException('Gateway is niet beschikbaar.');
    }
    foreach (['mx_status', 'spf_status', 'dkim_status', 'dmarc_status'] as $field) {
      if (($dnsChecks[$field] ?? NULL) !== 'ok') {
        throw new RuntimeException('DNS-controle niet gereed: ' . $field);
      }
    }
    if (!$mailstackValid) {
      throw new RuntimeException('Mailstackconfiguratie is niet geldig.');
    }

    return new CutoverPlan(
      $plan->domain,
      $plan->testDomain,
      $plan->cutoverAllowed,
      CutoverStatus::Ready,
    );
  }

  public function assertActivationAllowed(CutoverPlan $plan): void {
    if (!$plan->mayActivate()) {
      throw new RuntimeException('MX-cutover is geblokkeerd. Alleen een expliciet vrijgegeven testdomein in status ready mag worden geactiveerd.');
    }
  }
}
