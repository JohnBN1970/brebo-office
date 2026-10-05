<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\brebo_inzet\Contract\ProjectClockZoneRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal entity adapter for project clock-zone reads. */
final class DrupalProjectClockZoneRepository implements ProjectClockZoneRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function forProject(int $projectId): array {
    if ($projectId <= 0) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_clock_zone')
      ->condition('field_brebo_project_ref', $projectId)
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

      $building = $zone->hasField('field_brebo_building_ref')
        ? $zone->get('field_brebo_building_ref')->entity
        : NULL;

      $zones[] = [
        'id' => (int) $zone->id(),
        'name' => (string) $zone->label(),
        'building_id' => $building instanceof NodeInterface ? (int) $building->id() : 0,
        'building' => $building instanceof NodeInterface ? (string) $building->label() : '',
        'latitude' => (float) $latitude,
        'longitude' => (float) $longitude,
        'radius' => (float) $radius,
        'active' => (bool) $zone->get('field_brebo_zone_active')->value,
      ];
    }

    return $zones;
  }

}
