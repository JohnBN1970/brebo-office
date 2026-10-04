<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence and source-state boundary for financial project closure. */
interface ProjectFinancialClosureRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function latestForecast(int $projectNid): ?array;

  public function countOpenByStatus(int $projectNid, string $table, array $closedStatuses): int;

  /** @return array<string,mixed>|null */
  public function closure(int $projectNid): ?array;

  /** @param array<string,mixed> $payload
   *  @return array<string,mixed>
   */
  public function createClosure(array $payload): array;

  /** @param list<string> $sourceTables */
  public function financialSourceStateHash(int $projectNid, array $sourceTables): string;

}
