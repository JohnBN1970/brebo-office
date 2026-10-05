<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Read boundary for the project financial cockpit. */
interface FinancialCockpitReadRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function latestForecast(int $projectNid): ?array;

  /** @return list<array<string,mixed>> */
  public function latestScenarioSnapshots(int $projectNid): array;

  public function verifiedCostObservationCount(int $projectNid): int;

  /** @return list<string> */
  public function observedCostCodes(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function latestCostBenchmarks(int $projectNid): array;

  /** @return list<array<string,mixed>> */
  public function latestSupplierScores(int $projectNid): array;

  /** @return array<string,mixed>|null */
  public function latestCashForecast(int $projectNid, string $scenario): ?array;

  /** @param list<string> $statuses */
  public function sumByStatus(string $table, string $field, int $projectNid, array $statuses): string;

  /** @param list<string> $statuses */
  public function sumExceptStatus(string $table, string $field, int $projectNid, array $statuses): string;

  /** @param list<string> $statuses */
  public function countByStatus(string $table, int $projectNid, array $statuses, string $statusField = 'status'): int;

  /** @param list<string> $statuses */
  public function countExceptStatus(string $table, int $projectNid, array $statuses): int;

}
