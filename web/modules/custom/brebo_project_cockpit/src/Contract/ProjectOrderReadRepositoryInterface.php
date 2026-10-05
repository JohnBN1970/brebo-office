<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Contract;

/** Read boundary for order preparation against the locked working budget. */
interface ProjectOrderReadRepositoryInterface {

  /** @return list<array<string,mixed>> */
  public function workingBudgetLines(int $projectId): array;

}
