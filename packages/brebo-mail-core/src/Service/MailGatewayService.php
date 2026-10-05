<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Contract\MailGatewayInterface;

final class MailGatewayService {
  public function __construct(private readonly MailGatewayInterface $gateway) {}

  /** @return array{provider:string,available:bool,message:string} */
  public function health(): array {
    return $this->gateway->health();
  }

  /** @param array<string,mixed> $domain */
  public function provisionDomain(array $domain): string {
    return $this->gateway->provisionDomain($domain);
  }

  /** @param array<string,mixed> $mailbox */
  public function provisionMailbox(array $mailbox): string {
    return $this->gateway->provisionMailbox($mailbox);
  }

  public function provisionAlias(string $aliasAddress, string $targetAddress): string {
    return $this->gateway->provisionAlias($aliasAddress, $targetAddress);
  }
}
