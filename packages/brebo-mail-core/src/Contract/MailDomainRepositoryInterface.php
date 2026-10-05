<?php

declare(strict_types=1);

namespace Brebo\Mail\Contract;

interface MailDomainRepositoryInterface {
  /** @return array<int,array<string,mixed>> */
  public function all(): array;

  /** @return array<string,mixed>|null */
  public function load(int $domainId): ?array;

  public function create(string $domain, string $verificationToken): int;

  public function setStatus(int $domainId, string $status): void;

  public function setDkim(int $domainId, string $selector, string $publicKey): void;

  /** @param array<string,string> $checks */
  public function setDnsChecks(int $domainId, array $checks): void;
}
