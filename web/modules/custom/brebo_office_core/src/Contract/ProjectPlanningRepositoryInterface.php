<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Contract;

/** Persistence boundary for the published project planning model. */
interface ProjectPlanningRepositoryInterface {

  /**
   * Publishes one planning version and its normalized activities atomically.
   *
   * @param array<int,array<string,mixed>> $activities
   */
  public function publish(
    int $projectNid,
    string $sourceType,
    ?string $sourceReference,
    array $activities,
    int $uid,
    int $publishedAt,
  ): int;

  /** Quantity reserved from the given date through the required date. */
  public function reservedQuantity(
    int $projectNid,
    string $materialKey,
    string $fromDate,
    string $throughDate,
  ): float;

}
