<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Contract;

/** Persistence boundary for immutable external Calc publications. */
interface CalcResultSnapshotRepositoryInterface {

  /** @param array<string,mixed> $canonical */
  public function publish(int $calculationId, string $officeVersion, string $calcVersion, array $canonical, string $json, string $hash, int $actorId): array;

  public function latest(int $calculationId): ?array;

}
