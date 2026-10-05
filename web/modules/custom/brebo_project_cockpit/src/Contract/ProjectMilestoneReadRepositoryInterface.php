<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Contract;

/** Read boundary for ordered project route/milestone items. */
interface ProjectMilestoneReadRepositoryInterface {

  /**
   * @return list<array{
   *   id:int,
   *   label:string,
   *   status:string,
   *   phase:string,
   *   due:?string,
   *   kind:string,
   *   owner:?string,
   *   evidence:string
   * }>
   */
  public function routeItems(int $projectId): array;

}
