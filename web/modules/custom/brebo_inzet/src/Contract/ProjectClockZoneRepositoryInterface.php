<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Contract;

/** Read boundary for project clock-zone data. */
interface ProjectClockZoneRepositoryInterface {

  /**
   * @return array<int, array{id:int,name:string,building_id:int,building:string,latitude:float,longitude:float,radius:float,active:bool}>
   */
  public function forProject(int $projectId): array;

}
