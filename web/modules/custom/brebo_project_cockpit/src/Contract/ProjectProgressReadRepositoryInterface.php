<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Contract;

/** Read boundary for project progress source activities. */
interface ProjectProgressReadRepositoryInterface {

  /**
   * @return list<array{
   *   progress:?float,
   *   duration:?float,
   *   start:?string,
   *   baseline_end:?string,
   *   end:?string,
   *   status:string,
   *   critical:bool
   * }>
   */
  public function activities(int $projectId): array;

}
