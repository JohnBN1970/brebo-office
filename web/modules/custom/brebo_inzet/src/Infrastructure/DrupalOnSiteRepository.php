<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\OnSiteRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal node adapter for OnSite persistence. */
final class DrupalOnSiteRepository implements OnSiteRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function projectIdsForUser(int $userId, string $date): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $projectIds = [];

    $teamProjectIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_project')
      ->condition('status', 1)
      ->condition('field_brebo_project_team', $userId)
      ->execute();
    foreach ($teamProjectIds as $projectId) {
      $projectIds[(int) $projectId] = (int) $projectId;
    }

    $assignmentIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_personnel_assignment')
      ->condition('status', 1)
      ->condition('field_brebo_plan_user', $userId)
      ->condition('field_brebo_plan_date', $date)
      ->execute();
    foreach ($storage->loadMultiple($assignmentIds) as $assignment) {
      if (!$assignment instanceof NodeInterface) {
        continue;
      }
      $projectId = (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0);
      if ($projectId > 0) {
        $projectIds[$projectId] = $projectId;
      }
    }

    return array_values($projectIds);
  }

  public function projects(array $projectIds): array {
    $projects = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($projectIds) as $project) {
      if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
        continue;
      }
      $projects[] = ['id' => (int) $project->id(), 'name' => (string) $project->label()];
    }
    return $projects;
  }

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
      $radius = $zones === [] ? 150.0 : max(array_map(static fn (array $zone): float => (float) $zone['radius_metres'], $zones));
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

  public function clockZone(int $zoneId): ?array {
    $zone = $this->entityTypeManager->getStorage('node')->load($zoneId);
    if (!$zone instanceof NodeInterface || $zone->bundle() !== 'brebo_clock_zone') {
      return NULL;
    }
    return [
      'id' => (int) $zone->id(),
      'building_id' => $zone->hasField('field_brebo_building_ref') ? (int) ($zone->get('field_brebo_building_ref')->target_id ?? 0) : 0,
      'project_id' => (int) ($zone->get('field_brebo_project_ref')->target_id ?? 0),
    ];
  }

  public function validBuilding(int $buildingId): bool {
    $node = $this->entityTypeManager->getStorage('node')->load($buildingId);
    return $node instanceof NodeInterface && $node->bundle() === 'brebo_building';
  }

  public function validProject(int $projectId): bool {
    $node = $this->entityTypeManager->getStorage('node')->load($projectId);
    return $node instanceof NodeInterface && $node->bundle() === 'brebo_project';
  }

  public function createPresenceEvent(array $event): int {
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'brebo_onsite_presence_event',
      'title' => (string) $event['title'],
      'field_brebo_clock_user' => ['target_id' => (int) $event['user_id']],
      'field_brebo_project_ref' => !empty($event['project_id']) ? ['target_id' => (int) $event['project_id']] : NULL,
      'field_brebo_building_ref' => !empty($event['building_id']) ? ['target_id' => (int) $event['building_id']] : NULL,
      'field_brebo_clock_zone_ref' => !empty($event['zone_id']) ? ['target_id' => (int) $event['zone_id']] : NULL,
      'field_brebo_onsite_event_kind' => (string) $event['kind'],
      'field_brebo_onsite_occurred_at' => (string) $event['occurred_at'],
      'status' => 1,
    ]);
    $node->save();
    return (int) $node->id();
  }

}
