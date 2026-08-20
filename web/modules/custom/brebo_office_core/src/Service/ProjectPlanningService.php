<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;

/** Central published project planning interface for BREBO Office modules. */
final class ProjectPlanningService {
  public function __construct(private readonly Connection $database) {}

  /**
   * Publishes a reviewed planning version as the single project truth.
   *
   * @param array<int,array<string,mixed>> $activities
   */
  public function publish(int $projectNid, string $sourceType, ?string $sourceReference, array $activities, int $uid): int {
    $transaction = $this->database->startTransaction();
    $this->database->update('brebo_project_planning_version')
      ->fields(['is_published' => 0])
      ->condition('project_nid', $projectNid)
      ->execute();

    $versionId = (int) $this->database->insert('brebo_project_planning_version')->fields([
      'project_nid' => $projectNid,
      'source_type' => $sourceType,
      'source_reference' => $sourceReference,
      'is_published' => 1,
      'published_by' => $uid,
      'published_at' => time(),
    ])->execute();

    foreach ($activities as $activity) {
      $this->database->insert('brebo_project_planning_activity')->fields([
        'planning_version_id' => $versionId,
        'project_nid' => $projectNid,
        'activity_code' => (string) ($activity['activity_code'] ?? ''),
        'location_reference' => (string) ($activity['location_reference'] ?? ''),
        'activity_type' => (string) ($activity['activity_type'] ?? ''),
        'planned_date' => (string) ($activity['planned_date'] ?? ''),
        'quantity' => (float) ($activity['quantity'] ?? 0),
        'material_key' => isset($activity['material_key']) ? (string) $activity['material_key'] : NULL,
      ])->execute();
    }
    unset($transaction);
    return $versionId;
  }

  /** Quantity that must remain reserved through a date for a material key. */
  public function reservedQuantity(int $projectNid, string $materialKey, string $throughDate): float {
    $versionId = $this->database->select('brebo_project_planning_version', 'v')
      ->fields('v', ['id'])->condition('project_nid', $projectNid)->condition('is_published', 1)
      ->orderBy('published_at', 'DESC')->range(0, 1)->execute()->fetchField();
    if (!$versionId) {
      return 0.0;
    }
    $query = $this->database->select('brebo_project_planning_activity', 'a');
    $query->addExpression('COALESCE(SUM(a.quantity), 0)', 'reserved');
    $query->condition('planning_version_id', (int) $versionId)
      ->condition('material_key', $materialKey)
      ->condition('planned_date', $throughDate, '<=');
    return (float) $query->execute()->fetchField();
  }
}
