<?php

declare(strict_types=1);

namespace Drupal\brebo_resident_service\Contract;

/** Read boundary for resident/access readiness source data. */
interface ResidentAccessReadRepositoryInterface {

  public function accessForScope(string $scopeType, int $scopeId, ?int $projectId = NULL): ?array;

  /**
   * @return list<array{id:int,address_line:string,occupancy_status:string}>
   */
  public function residencesForZone(int $buildingNid, int $technicalZoneId): array;

  /**
   * @return array{id:int,project_id:?int,technical_zone_id:?int}|null
   */
  public function workPackage(int $packageId): ?array;

  public function buildingForZone(int $technicalZoneId): ?int;

  /**
   * @return list<array{id:int,label:string,planned_start:string}>
   */
  public function workPackagesForProject(int $projectId): array;

  public function timezoneName(): string;

}
