<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

interface IntakeReviewRepositoryInterface {
  /** @return array<int,array<string,mixed>> */
  public function pending(int $page = 0, int $limit = 50): array;
  public function pendingCount(): int;
}
