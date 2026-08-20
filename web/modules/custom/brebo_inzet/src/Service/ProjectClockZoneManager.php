<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Loads project clock zones from durable BREBO Office project data.
 */
final class ProjectClockZoneManager {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns active and inactive clock zones belonging to a project.
   *
   * The manager deliberately keeps storage concerns out of the geofence
   * calculation service. A clock registration can therefore ask for project
   * zones once and pass the normalized result to ProjectClockZoneControl.
   *
   * @return array<int, array{id: int, name: string, latitude: float, longitude: float, radius: float, active: bool}>
   */
  public function loadForProject(NodeInterface $project): array {
    if ($project->bundle() !== 'brebo_project') {
      throw new \InvalidArgumentException('Clock zones can only be loaded for a BREBO project.');
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_clock_zone')
      ->condition('field_brebo_project_ref', (int) $project->id())
      ->sort('title')
      ->execute();

    $zones = [];
    foreach ($storage->loadMultiple($ids) as $zone) {
      if (!$zone instanceof NodeInterface) {
        continue;
      }

      $latitude = $zone->get('field_brebo_zone_latitude')->value;
      $longitude = $zone->get('field_brebo_zone_longitude')->value;
      $radius = $zone->get('field_brebo_zone_radius')->value;
      if ($latitude === NULL || $longitude === NULL || $radius === NULL) {
        continue;
      }

      $zones[] = [
        'id' => (int) $zone->id(),
        'name' => $zone->label(),
        'latitude' => (float) $latitude,
        'longitude' => (float) $longitude,
        'radius' => (float) $radius,
        'active' => (bool) $zone->get('field_brebo_zone_active')->value,
      ];
    }

    return $zones;
  }

}
