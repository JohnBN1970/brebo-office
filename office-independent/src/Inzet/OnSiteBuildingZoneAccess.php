<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Buildings\OfficeBuildingZoneRepository;
use Closure;
use InvalidArgumentException;

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
    try {
      return $this->zones->forProjectZone($projectId, $zoneId, $this->authorizedProject) !== null;
    } catch (InvalidArgumentException $exception) {
      // Access checks return false rather than propagating authorization errors.
      return false;
    }
  }

  public function callback(): Closure {
    return fn(string $projectId, string $zoneId): bool =>
      $this->isActiveForProject($projectId, $zoneId);
  }
}
