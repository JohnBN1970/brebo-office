<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persists deterministic financial phase-gate state without exposing storage details. */
interface FinancialPhaseGateRepositoryInterface {

  public function ensureStorage(): void;

  /** @param list<string> $highControlCodes
   *  @return list<array<string,mixed>>
   */
  public function blockingFindings(int $projectId, array $highControlCodes): array;

  /** @return array<string,mixed>|null */
  public function activeException(int $projectId, string $gate, int $now): ?array;

  /** @param array<string,mixed> $fields */
  public function createException(array $fields): int;

  /** @return array<string,mixed>|null */
  public function exception(int $exceptionId): ?array;

  /** @param array<string,mixed> $fields */
  public function updateException(int $exceptionId, array $fields): void;

  /** @param array<string,mixed> $fields */
  public function appendAudit(array $fields): void;

}
