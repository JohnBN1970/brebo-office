<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface MailboxStorageRepositoryInterface {
  /** @return list<array<string,mixed>> */
  public function mailboxes(): array;
  /** @return array<string,mixed>|null */
  public function mailbox(int $mailboxId): ?array;
  public function projectionStorageAvailable(): bool;
  /** @return list<array{id:int,address:string}> */
  public function activeMailboxes(): array;
  /** @return list<string> */
  public function activeMailboxAddresses(): array;
  /** @param array<string,mixed> $fields */
  public function upsertMessageProjection(int $mailboxId, int $communicationId, array $fields): void;
  /** @return list<string> */
  public function allowedRoles(int $mailboxId, string $capability = 'view'): array;
  /** @return list<array<string,mixed>> */
  public function messageRows(int $mailboxId, string $state, int $offset, int $limit): array;
  /** @return array<int,list<string>> */
  public function tagsForCommunications(array $communicationIds): array;
  public function messageBelongsToMailbox(int $mailboxId, int $communicationId): bool;
  /** @return list<string> */
  public function tags(int $communicationId): array;
  /** @return list<array<string,mixed>> */
  public function documentAttachments(int $communicationId): array;
  /** @return list<array<string,mixed>> */
  public function search(array $visibleMailboxIds, string $term, int $mailboxId, string $state, int $limit = 100): array;
  /** @return array<string,mixed>|null */
  public function messageState(int $mailboxId, int $communicationId): ?array;
  /** @param array<string,mixed> $fields */
  public function updateMessage(int $mailboxId, int $communicationId, array $fields): void;
  /** @param list<string> $tags */
  public function replaceTags(int $communicationId, array $tags, int $uid): void;
  /** @return list<int> */
  public function linkedDocumentIds(int $communicationId): array;
  /** @param list<int> $documentIds */
  public function clearManualDocumentContexts(array $documentIds): void;
  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;
}
