<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface MailDomainRepositoryInterface {

  /** @return array<int, array<string,mixed>> */
  public function all(): array;

  /** @return array<string,mixed>|null */
  public function load(int $domainId): ?array;

  public function create(string $domain, string $verificationToken): int;

  public function setStatus(int $domainId, string $status): void;

  /** @param array<string,string> $checks */
  public function setDnsChecks(int $domainId, array $checks): void;

}
