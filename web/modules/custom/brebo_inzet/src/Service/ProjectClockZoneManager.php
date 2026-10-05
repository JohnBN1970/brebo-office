<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\ProjectClockZoneRepositoryInterface;

/** Loads normalized project clock zones for BREBO Office consumers. */
final class ProjectClockZoneManager {

  public function __construct(
    private readonly ProjectClockZoneRepositoryInterface $zoneRepository,
  ) {}

  /**
   * @return array<int, array{id:int,name:string,building_id:int,building:string,latitude:float,longitude:float,radius:float,active:bool}>
   */
  public function loadForProject(int $projectId): array {
    if ($projectId <= 0) {
      throw new \InvalidArgumentException('Clock zones can only be loaded for a valid BREBO project.');
    }

    return $this->zoneRepository->forProject($projectId);
  }

}
