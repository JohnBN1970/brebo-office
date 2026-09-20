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

}
