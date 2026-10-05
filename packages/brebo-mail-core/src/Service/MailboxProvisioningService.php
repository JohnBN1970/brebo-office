<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Contract\MailboxProvisioningRepositoryInterface;
use Brebo\Mail\Contract\MailDomainRepositoryInterface;
use Brebo\Mail\Domain\MailAddress;
use InvalidArgumentException;
use RuntimeException;

final class MailboxProvisioningService {
  public function __construct(
    private readonly MailboxProvisioningRepositoryInterface $repository,
    private readonly MailDomainRepositoryInterface $domains,
  ) {}

  public function createMailbox(string $localPart, int $domainId, string $label, string $privacyType = 'functional', int $ownerUid = 0): int {
    $domain = $this->verifiedDomain($domainId);
    $address = MailAddress::fromParts($localPart, (string) $domain['domain'])->value;
    if ($this->repository->addressInUse($address)) {
      throw new InvalidArgumentException('Dit e-mailadres is al in gebruik als mailbox of alias.');
    }

    $base = preg_replace('/[^a-z0-9_]+/', '_', str_replace(['.', '-'], '_', $address)) ?: 'mailbox';
    $machineName = substr(trim($base, '_'), 0, 53) . '_' . substr(hash('sha256', $address), 0, 10);

    return $this->repository->createMailbox(
      $machineName,
      trim($label) ?: $address,
      $address,
      $privacyType,
      $ownerUid,
    );
  }

  public function addAlias(int $mailboxId, string $localPart, int $domainId): int {
    if (!$this->repository->mailboxExists($mailboxId)) {
      throw new InvalidArgumentException('Doelmailbox bestaat niet.');
    }

    $domain = $this->verifiedDomain($domainId);
    $address = MailAddress::fromParts($localPart, (string) $domain['domain'])->value;
    if ($this->repository->addressInUse($address)) {
      throw new InvalidArgumentException('Dit e-mailadres is al in gebruik als mailbox of alias.');
    }

    return $this->repository->createAlias($mailboxId, $address);
  }

  /** @return array<int,array<string,mixed>> */
  public function aliases(int $mailboxId): array {
    return $this->repository->aliases($mailboxId);
  }

  /** @return array<string,mixed> */
  private function verifiedDomain(int $domainId): array {
    $domain = $this->domains->load($domainId);
    if (!$domain || (string) ($domain['status'] ?? '') !== 'verified') {
      throw new RuntimeException('Mailboxen en aliassen vereisen een geverifieerd maildomein.');
    }
    return $domain;
  }
}
