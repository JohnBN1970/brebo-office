<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Builds the minimal Office assignment payload needed by OnSite.
 */
final class OnSiteAssignmentProvider {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ProjectClockZoneManager $zoneManager,
  ) {}

  /**
   * @return array<int, array{id: string, name: string, zones: array<int, array{id: string, name: string, latitude: float, longitude: float, radius_metres: float}>}>
   */
  public function currentForUser(int $uid, ?string $date = NULL): array {
    $date ??= (new DrupalDateTime('now'))->format('Y-m-d');
    $storage = $this->entityTypeManager->getStorage('node');

    $assignmentIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('status', 1)
      ->condition('field_brebo_plan_user', $uid)
      ->condition('field_brebo_plan_date', $date)
      ->execute();

    $projectIds = [];

    // A durable project-team relation makes a project available to OnSite
    // without requiring a synthetic daily planning record.
    $teamProjectIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->condition('field_brebo_project_team', $uid)
      ->execute();
    foreach ($teamProjectIds as $projectId) {
      $projectIds[(int) $projectId] = (int) $projectId;
    }

    // Daily assignments remain additive: a person can be scheduled on a
    // project even when they are not part of its durable core team.
    foreach ($storage->loadMultiple($assignmentIds) as $assignment) {
      if (!$assignment instanceof NodeInterface) {
        continue;
      }
      $projectId = (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0);
      if ($projectId > 0) {
        $projectIds[$projectId] = $projectId;
      }
    }

    $projects = [];
    foreach ($storage->loadMultiple($projectIds) as $project) {
      if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
        continue;
      }

      $zones = [];
      foreach ($this->zoneManager->loadForProject($project) as $zone) {
        if (!$zone['active']) {
          continue;
        }
        $zones[] = [
          'id' => (string) $zone['id'],
          'name' => $zone['name'],
          'latitude' => $zone['latitude'],
          'longitude' => $zone['longitude'],
          'radius_metres' => $zone['radius'],
        ];
      }

      $projects[] = [
        'id' => (string) $project->id(),
        'name' => $project->label(),
        'zones' => $zones,
      ];
    }

    return $projects;
  }

  /**
   * Returns known BREBO buildings and personnel zones that the app may use
   * for a user-initiated clock action. This catalogue is deliberately
   * independent of project planning, but MUST NOT be used for background
   * location tracking or automatic presence registration.
   *
   * Explicit personnel zones override the default building radius.
   *
   * @return array<int, array{id:string,name:string,latitude:float,longitude:float,radius_metres:float,zones:array<int,array<string,mixed>>}>
   */
  public function clockLocations(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $buildingIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_building')
      ->condition('status', 1)
      ->execute();

    $zonesByBuilding = [];
    $zoneIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_zone')
      ->condition('status', 1)
      ->condition('field_brebo_zone_active', 1)
      ->exists('field_brebo_building_ref')
      ->execute();
    foreach ($storage->loadMultiple($zoneIds) as $zone) {
      if (!$zone instanceof NodeInterface || !$zone->hasField('field_brebo_building_ref')) {
        continue;
      }
      $buildingId = (int) ($zone->get('field_brebo_building_ref')->target_id ?? 0);
      $latitude = (float) ($zone->get('field_brebo_zone_latitude')->value ?? 0);
      $longitude = (float) ($zone->get('field_brebo_zone_longitude')->value ?? 0);
      $radius = (float) ($zone->get('field_brebo_zone_radius')->value ?? 0);
      if ($buildingId <= 0 || $latitude === 0.0 || $longitude === 0.0 || $radius <= 0.0) {
        continue;
      }
      $projectId = (int) ($zone->get('field_brebo_project_ref')->target_id ?? 0);
      $zonesByBuilding[$buildingId][] = [
        'id' => (string) $zone->id(),
        'name' => (string) $zone->label(),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'radius_metres' => $radius,
        'project_id' => $projectId > 0 ? (string) $projectId : NULL,
      ];
    }

    $buildings = [];
    foreach ($storage->loadMultiple($buildingIds) as $building) {
      if (!$building instanceof NodeInterface) {
        continue;
      }
      $latitude = $building->hasField('field_brebo_latitude') ? (float) ($building->get('field_brebo_latitude')->value ?? 0) : 0.0;
      $longitude = $building->hasField('field_brebo_longitude') ? (float) ($building->get('field_brebo_longitude')->value ?? 0) : 0.0;
      if ($latitude === 0.0 || $longitude === 0.0) {
        continue;
      }
      $zones = $zonesByBuilding[(int) $building->id()] ?? [];
      $radius = 150.0;
      if ($zones !== []) {
        $radius = max(array_map(static fn (array $zone): float => (float) $zone['radius_metres'], $zones));
      }
      $buildings[] = [
        'id' => (string) $building->id(),
        'name' => (string) $building->label(),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'radius_metres' => $radius,
        'zones' => $zones,
      ];
    }
    return $buildings;
  }


}
