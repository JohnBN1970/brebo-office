<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface FinancialEuroTraceRepositoryInterface {
  /** @return array<string,mixed> */
  public function one(string $table, int $id): array;
  /** @return list<array<string,mixed>> */
  public function many(string $table, string $field, int $value): array;
  /** @param list<int> $values @return list<array<string,mixed>> */
  public function manyIn(string $table, string $field, array $values): array;
  /** @param list<string> $types @param list<int> $ids @return list<array<string,mixed>> */
  public function audit(int $projectNid, array $types, array $ids): array;
}
