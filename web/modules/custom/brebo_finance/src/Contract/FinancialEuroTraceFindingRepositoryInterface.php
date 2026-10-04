<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

/** Persistence boundary for Euro Trace control findings. */
interface FinancialEuroTraceFindingRepositoryInterface {

  /** @return array<string,mixed>|null */
  public function activeFinding(int $projectNid, string $controlCode): ?array;

  public function updateFinding(int $findingId, array $fields): void;

  public function createFinding(array $fields): int;

  /** @return list<array{id:int,control_code:string}> */
  public function staleFindings(int $projectNid, array $knownCodes, array $activeCodes): array;

}
