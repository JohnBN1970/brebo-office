<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface SalesInvoiceOutboxRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function outbox(int $outboxId): ?array;

  /** @param array<string,mixed> $fields */
  public function updateOutbox(int $outboxId, array $fields): void;

  /** @param array<string,mixed> $fields */
  public function updateDraft(int $draftId, array $fields): void;

  /** @param callable():mixed $callback */
  public function transactional(callable $callback): mixed;

}
