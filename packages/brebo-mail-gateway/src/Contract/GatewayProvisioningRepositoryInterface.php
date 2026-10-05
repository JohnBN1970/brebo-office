<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

interface GatewayProvisioningRepositoryInterface {

  /** @param array<string,mixed> $payload */
  public function provisionDomain(array $payload): string;

  /** @param array<string,mixed> $payload */
  public function provisionMailbox(array $payload): string;

  public function provisionAlias(string $aliasAddress, string $targetAddress): string;
}
