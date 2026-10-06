<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Contract;

interface GlassPositionPersistenceInterface {

  public function currentTime(): int;

  public function isNodeBundle(int $nid, string $bundle): bool;

  /** @param array<string,mixed> $values */
  public function insert(array $values): int;

  /**
   * @return array<int,array<string,mixed>>
   */
  public function findAll(string $search, string $status, string $column, string $order, int $limit): array;

  /** @return array<string,int> */
  public function countByStatus(): array;

  /** @return array<string,mixed>|null */
  public function find(int $id): ?array;

  /** @param array<string,mixed> $values */
  public function approveMeasured(int $id, array $values): int;

}
