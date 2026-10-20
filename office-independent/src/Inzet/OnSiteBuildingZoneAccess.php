<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Buildings\OfficeBuildingZoneRepository;
use Closure;

/**
 * Adapter for existing Inzet callbacks: zone belongs to a building,
 * project merely grants access through office_project_buildings.
 */
final class OnSiteBuildingZoneAccess {
  public function __construct(
    private OfficeBuildingZoneRepository $zones,
    private Closure $authorizedProject
  ) {}

  public function isActiveForProject(string $projectId, string $zoneId): bool {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $projectId)
      || !preg_match('/^[1-9][0-9]{0,18}$/D', $zoneId)) return false;
    return $this->zones->forProjectZone($projectId, $zoneId, $this->authorizedProject) !== null;
  }

  public function callback(): Closure {
    return fn(string $projectId, string $zoneId): bool =>
      $this->isActiveForProject($projectId, $zoneId);
  }
}
