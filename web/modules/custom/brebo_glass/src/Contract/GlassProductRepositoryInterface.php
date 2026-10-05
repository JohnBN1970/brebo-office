<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Contract;

interface GlassProductRepositoryInterface {

  /** @param array<string,mixed> $values */
  public function insert(array $values): int;

  /** @return array<string,mixed>|null */
  public function find(int $id): ?array;

  public function verify(int $id, int $userId, string $note): void;

  /** @return array<int,array<string,mixed>> */
  public function activeVerifiedCandidates(): array;

  /** @return array<int,array<string,mixed>> */
  public function findAll(): array;

}
