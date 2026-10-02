<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FinancialNotificationOutboxRepositoryInterface {
  public function ensureStorage(): void;
  public function existsByDedupeKey(string $dedupeKey): bool;
  /** @param array<string,mixed> $fields */
  public function create(array $fields): int;
  public function markReady(int $id,int $changed): void;
  public function markRetry(int $id,string $error,int $changed): void;
  /** @return list<array<string,mixed>> */
  public function forUser(int $uid,bool $unreadOnly): array;
  public function markReadForUser(int $id,int $uid,int $changed): bool;
  public function unreadCount(int $uid): int;
  /** @return array<string,mixed>|null */
  public function load(int $id): ?array;
}
