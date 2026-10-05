<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Contract;

interface ControlHistoryRepositoryInterface {

  public function latestCapturedAt(int $projectId): ?int;

  /** @param array<string,mixed> $fields */
  public function append(int $projectId, int $capturedAt, array $fields): void;

  /** @return array<int,array<string,mixed>> */
  public function snapshots(int $projectId, int $limit): array;

}
