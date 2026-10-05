<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface StandaloneSalesInvoiceRepositoryInterface {
  public function draftStorageAvailable(): bool;
  public function releaseStorageAvailable(): bool;
  /** @return array<string,mixed>|null */
  public function editableDraft(int $draftId): ?array;
  /** @return array<string,mixed>|null */
  public function draft(int $draftId): ?array;
  /** @return list<array<string,mixed>> */
  public function lines(int $draftId): array;
  /** @param array<string,mixed> $draftFields @param list<array<string,mixed>> $lineFields */
  public function saveDraft(int $draftId, string $draftNumber, array $draftFields, array $lineFields): int;
  /** @param array<string,mixed> $outboxFields @param array<string,mixed> $draftFields */
  public function queueRelease(int $draftId, array $outboxFields, array $draftFields): int;
}
