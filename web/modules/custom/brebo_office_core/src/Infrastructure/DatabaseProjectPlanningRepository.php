<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\ProjectPlanningRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for the canonical published project planning model. */
final class DatabaseProjectPlanningRepository implements ProjectPlanningRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function publish(
    int $projectNid,
    string $sourceType,
    ?string $sourceReference,
    array $activities,
    int $uid,
    int $publishedAt,
  ): int {
    $this->ensureSchema();

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
      'published_at' => $publishedAt,
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
        'material_key' => $activity['material_key'] ?? NULL,
      ])->execute();
    }

    unset($transaction);
    return $versionId;
  }

  public function reservedQuantity(
    int $projectNid,
    string $materialKey,
    string $fromDate,
    string $throughDate,
  ): float {
    $this->ensureSchema();

    $versionId = $this->database->select('brebo_project_planning_version', 'v')
      ->fields('v', ['id'])
      ->condition('project_nid', $projectNid)
      ->condition('is_published', 1)
      ->orderBy('published_at', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!$versionId) {
      return 0.0;
    }

    $query = $this->database->select('brebo_project_planning_activity', 'a');
    $query->addExpression('COALESCE(SUM(a.quantity), 0)', 'reserved');
    $query->condition('planning_version_id', (int) $versionId)
      ->condition('material_key', $materialKey)
      ->condition('planned_date', $fromDate, '>=')
      ->condition('planned_date', $throughDate, '<=');

    return (float) $query->execute()->fetchField();
  }

  private function ensureSchema(): void {
    $schema = $this->database->schema();
    if (!$schema->tableExists('brebo_project_planning_version')) {
      $schema->createTable('brebo_project_planning_version', [
        'description' => 'Versioned project plannings; only one version is published per project.',
        'fields' => [
          'id' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
          'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'source_type' => ['type' => 'varchar_ascii', 'length' => 32, 'not null' => TRUE],
          'source_reference' => ['type' => 'varchar', 'length' => 255, 'not null' => FALSE],
          'is_published' => ['type' => 'int', 'size' => 'tiny', 'unsigned' => TRUE, 'not null' => TRUE, 'default' => 0],
          'published_by' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'published_at' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'project_published' => ['project_nid', 'is_published'],
          'published_at' => ['published_at'],
        ],
      ]);
    }

    if (!$schema->tableExists('brebo_project_planning_activity')) {
      $schema->createTable('brebo_project_planning_activity', [
        'description' => 'Normalized activities belonging to a project planning version.',
        'fields' => [
          'id' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
          'planning_version_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'project_nid' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
          'activity_code' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE, 'default' => ''],
          'location_reference' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE, 'default' => ''],
          'activity_type' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => TRUE, 'default' => ''],
          'planned_date' => ['type' => 'varchar_ascii', 'length' => 10, 'not null' => TRUE],
          'quantity' => ['type' => 'numeric', 'precision' => 12, 'scale' => 3, 'not null' => TRUE, 'default' => 0],
          'material_key' => ['type' => 'varchar_ascii', 'length' => 64, 'not null' => FALSE],
        ],
        'primary key' => ['id'],
        'indexes' => [
          'version' => ['planning_version_id'],
          'project_date' => ['project_nid', 'planned_date'],
          'material_date' => ['material_key', 'planned_date'],
        ],
      ]);
    }
  }

}
