<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Contract;

interface MailStackAdapterInterface {

  /** @param array<string,mixed> $domain */
  public function applyDomain(array $domain): void;

  /** @param array<string,mixed> $mailbox */
  public function applyMailbox(array $mailbox): void;

  public function applyAlias(string $aliasAddress, string $targetAddress): void;

  /** @return array{available:bool,message:string} */
  public function health(): array;
}
