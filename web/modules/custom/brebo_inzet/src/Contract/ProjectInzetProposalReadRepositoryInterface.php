<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Read boundary for workforce proposal source data. */
interface ProjectInzetProposalReadRepositoryInterface {

  /**
   * @return array{start:?string,end:?string,budget_hours:float}
   */
  public function source(int $projectId): array;

}
