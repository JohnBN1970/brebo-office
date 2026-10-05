<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for the Finance Digital Controller. */
interface DigitalControllerRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function latestForecast(int $projectNid): ?array;

  /** @return list<array<string,mixed>> */
  public function openFindings(int $projectNid): array;

  public function pendingAiCount(int $projectNid): int;

  /** @return list<array<string,mixed>> */
  public function paymentExceptions(int $projectNid): array;

  /** @return array<string,mixed> */
  public function budgetState(int $projectNid): array;

  /** @param array<string,mixed> $fields */
  public function saveScheduledRun(int $projectNid, string $runDate, array $fields): int;

}
