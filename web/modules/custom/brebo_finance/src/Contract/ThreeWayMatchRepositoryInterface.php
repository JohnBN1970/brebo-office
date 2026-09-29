<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface ThreeWayMatchRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function invoiceLineContext(int $invoiceLineId): ?array;

  public function verifiedPerformanceAmount(int $commitmentLineId): string;

  public function previouslyMatchedAmount(int $commitmentLineId, int $excludeLineId): string;

  /** @param array<string,mixed> $values */
  public function updateInvoiceLine(int $invoiceLineId, array $values): void;

  /** @return array{exceptions:int,unmatched:int} */
  public function invoiceMatchCounts(int $invoiceId): array;

  /** @param array<string,mixed> $values */
  public function updateInvoice(int $invoiceId, array $values): void;

  /** @param array<string,mixed> $values */
  public function insertAudit(array $values): void;

}
