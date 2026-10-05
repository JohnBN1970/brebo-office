<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\MailboxProvisioningRepositoryInterface;
use Drupal\brebo_mail_intake\Contract\MailDomainRepositoryInterface;

final class MailboxProvisioningService {
  public function __construct(
    private readonly MailboxProvisioningRepositoryInterface $repository,
    private readonly MailDomainRepositoryInterface $domains,
  ) {}

  public function createMailbox(string $localPart, int $domainId, string $label, string $privacyType = 'functional', int $ownerUid = 0): int {
    $domain = $this->domains->load($domainId);
    if (!$domain || (string) ($domain['status'] ?? '') !== 'verified') {
      throw new \RuntimeException('Mailboxen kunnen alleen onder een geverifieerd maildomein worden aangemaakt.');
    }
    $localPart = mb_strtolower(trim($localPart));
    if (!preg_match('/^[a-z0-9._+-]+$/', $localPart)) {
      throw new \InvalidArgumentException('Ongeldig mailboxadres.');
    }
    $address = $localPart . '@' . mb_strtolower((string) $domain['domain']);
    if ($this->repository->addressInUse($address)) {
      throw new \InvalidArgumentException('Dit e-mailadres is al in gebruik als mailbox of alias.');
    }

    $base = preg_replace('/[^a-z0-9_]+/', '_', str_replace(['.', '-'], '_', $localPart . '_' . $domain['domain'])) ?: 'mailbox';
    $suffix = substr(hash('sha256', $address), 0, 10);
    $machine = substr(trim($base, '_'), 0, 53) . '_' . $suffix;

    return $this->repository->createMailbox($machine, trim($label) ?: $address, $address, $privacyType, $ownerUid);
  }

  public function addAlias(int $mailboxId, string $localPart, int $domainId): int {
    if (!$this->repository->mailboxExists($mailboxId)) {
      throw new \InvalidArgumentException('Doelmailbox bestaat niet.');
    }

    $domain = $this->domains->load($domainId);
    if (!$domain || (string) ($domain['status'] ?? '') !== 'verified') {
      throw new \RuntimeException('Aliases kunnen alleen onder een geverifieerd maildomein worden aangemaakt.');
    }
    $localPart = mb_strtolower(trim($localPart));
    if (!preg_match('/^[a-z0-9._+-]+$/', $localPart)) {
      throw new \InvalidArgumentException('Ongeldig aliasadres.');
    }
    $address = $localPart . '@' . mb_strtolower((string) $domain['domain']);
    if ($this->repository->addressInUse($address)) {
      throw new \InvalidArgumentException('Dit e-mailadres is al in gebruik als mailbox of alias.');
    }
    return $this->repository->createAlias($mailboxId, $address);
  }

  /** @return array<int,array<string,mixed>> */
  public function aliases(int $mailboxId): array {
    return $this->repository->aliases($mailboxId);
  }
}
