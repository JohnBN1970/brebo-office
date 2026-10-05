<?php

declare(strict_types=1);

namespace Brebo\Mail\Contract;

interface MailboxProvisioningRepositoryInterface {
  public function createMailbox(string $machineName, string $label, string $address, string $privacyType = 'functional', int $ownerUid = 0): int;
  public function mailboxExists(int $mailboxId): bool;
  public function addressInUse(string $address): bool;
  public function createAlias(int $mailboxId, string $address): int;

  /** @return array<int,array<string,mixed>> */
  public function aliases(int $mailboxId): array;
}
