<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Infrastructure;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Trusted server-side source snapshot for Office canonical zone import.
 * This class does not make HTTP calls or expose a public/mobile endpoint.
 * Project identity is resolved independently on the Office side.
 */
final class DrupalClockZoneOfficeExport {

  public function __construct(private readonly EntityTypeManagerInterface $entities) {}

  /** @return array<int, array<string, mixed>> */
  public function forProject(int $sourceProjectId): array {
    if ($sourceProjectId < 1) return [];
    $storage = $this->entities->getStorage('node');
    $project = $storage->load($sourceProjectId);
    if (!$project instanceof NodeInterface || $project->bundle() !== 'brebo_project') {
      return [];
    }
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_clock_zone')
      ->condition('field_brebo_project_ref', $sourceProjectId)
      ->execute();
    $result = [];
    foreach ($storage->loadMultiple($ids) as $zone) {
      if (!$zone instanceof NodeInterface || $zone->bundle() !== 'brebo_clock_zone') continue;
      $revision = $zone->getRevisionId();
      if (!is_numeric($revision) || (int) $revision < 1) continue;
      $active = (bool) $zone->get('field_brebo_zone_active')->value
        && $zone->isPublished();
      $result[] = [
        'zone_id' => (int) $zone->id(),
        'source_project_id' => $sourceProjectId,
        'source_revision' => (int) $revision,
        'active' => $active,
        'zone_name' => (string) $zone->label(),
        'latitude' => $active ? $zone->get('field_brebo_zone_latitude')->value : NULL,
        'longitude' => $active ? $zone->get('field_brebo_zone_longitude')->value : NULL,
        'radius_metres' => $active ? $zone->get('field_brebo_zone_radius')->value : NULL,
      ];
    }
    return $result;
  }
}
