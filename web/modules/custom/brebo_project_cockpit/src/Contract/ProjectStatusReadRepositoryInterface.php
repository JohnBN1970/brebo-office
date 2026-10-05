<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Contract;

/** Read boundary for operational project-status source data. */
interface ProjectStatusReadRepositoryInterface {

  /**
   * @param string[] $statusFields
   * @return array{available:bool,total:int,status_values:string[]}
   */
  public function domainStatusValues(string $bundle, int $projectId, array $statusFields): array;

  /** @return array{available:bool,total:int,red:int,orange:int} */
  public function clockStatusCounts(int $projectId): array;

}
