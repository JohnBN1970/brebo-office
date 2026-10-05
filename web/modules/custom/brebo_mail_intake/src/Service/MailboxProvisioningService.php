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
    if ($this->repository->mailboxIdByAddress($address) !== NULL) {
      throw new \InvalidArgumentException('Dit mailboxadres bestaat al.');
    }
    $machine = preg_replace('/[^a-z0-9_]+/', '_', str_replace(['.', '-'], '_', $localPart . '_' . $domain['domain'])) ?: 'mailbox_' . bin2hex(random_bytes(4));
    return $this->repository->createMailbox($machine, trim($label) ?: $address, $address, $privacyType, $ownerUid);
  }

  public function addAlias(int $mailboxId, string $localPart, int $domainId): int {
    $domain = $this->domains->load($domainId);
    if (!$domain || (string) ($domain['status'] ?? '') !== 'verified') {
      throw new \RuntimeException('Aliases kunnen alleen onder een geverifieerd maildomein worden aangemaakt.');
    }
    $localPart = mb_strtolower(trim($localPart));
    if (!preg_match('/^[a-z0-9._+-]+$/', $localPart)) {
      throw new \InvalidArgumentException('Ongeldig aliasadres.');
    }
    $address = $localPart . '@' . mb_strtolower((string) $domain['domain']);
    if ($this->repository->mailboxIdByAddress($address) !== NULL) {
      throw new \InvalidArgumentException('Dit adres is al een primaire mailbox.');
    }
    return $this->repository->createAlias($mailboxId, $address);
  }
  /** @return array<int,array<string,mixed>> */
  public function aliases(int $mailboxId): array {
    return $this->repository->aliases($mailboxId);
  }

}
