<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailGatewayInterface;

final class DeferredMailGateway implements MailGatewayInterface {

  public function health(): array {
    return [
      'provider' => 'deferred',
      'available' => TRUE,
      'message' => 'BREBO Office beheert de logische mailconfiguratie; externe gateway-provisioning is nog niet geactiveerd.',
    ];
  }

  public function provisionDomain(array $domain): string {
    return $this->reference('domain', (string) ($domain['domain'] ?? ''));
  }

  public function provisionMailbox(array $mailbox): string {
    return $this->reference('mailbox', (string) ($mailbox['address'] ?? ''));
  }

  public function provisionAlias(string $aliasAddress, string $targetAddress): string {
    return $this->reference('alias', $aliasAddress . '>' . $targetAddress);
  }

  private function reference(string $type, string $identity): string {
    return 'deferred:' . $type . ':' . substr(hash('sha256', mb_strtolower(trim($identity))), 0, 20);
  }

}
