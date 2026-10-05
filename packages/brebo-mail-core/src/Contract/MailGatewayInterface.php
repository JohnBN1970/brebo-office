<?php

declare(strict_types=1);

namespace Brebo\Mail\Contract;

interface MailGatewayInterface {
  /** @return array{provider:string,available:bool,message:string} */
  public function health(): array;

  /** @param array<string,mixed> $domain */
  public function provisionDomain(array $domain): string;

  /** @param array<string,mixed> $mailbox */
  public function provisionMailbox(array $mailbox): string;

  public function provisionAlias(string $aliasAddress, string $targetAddress): string;
}
