<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

interface ControlFindingReadRepositoryInterface {

  /** @return list<array<string,mixed>> */
  public function openForProject(int $projectId): array;

}
