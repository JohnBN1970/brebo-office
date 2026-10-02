<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface PerformanceEvidenceReviewRepositoryInterface {
  /** @return array<string,mixed>|null */
  public function receipt(int $id): ?array;
  public function saveReview(int $receiptId,string $evidenceRef,array $fields): void;
  /** @return list<array<string,mixed>> */
  public function reviews(int $receiptId): array;
}
