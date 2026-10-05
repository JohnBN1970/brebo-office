<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Infrastructure;

use Drupal\brebo_project_cockpit\Contract\ProjectInstalmentRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for project instalment workflows. */
final class DatabaseProjectInstalmentRepository implements ProjectInstalmentRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function instalmentForProject(int $instalmentId, int $projectId): ?array {
    if (!$this->database->schema()->tableExists('brebo_finance_billing_instalment')) {
      return NULL;
    }
    $row = $this->database->select('brebo_finance_billing_instalment', 'i')
      ->fields('i')
      ->condition('id', $instalmentId)
      ->condition('project_nid', $projectId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function instalmentCount(int $projectId): int {
    if (!$this->database->schema()->tableExists('brebo_finance_billing_instalment')) {
      return 0;
    }
    return (int) $this->database->select('brebo_finance_billing_instalment', 'i')
      ->condition('project_nid', $projectId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function updateInstalmentForProject(int $instalmentId, int $projectId, array $fields): bool {
    return $this->database->update('brebo_finance_billing_instalment')
      ->fields($fields)
      ->condition('id', $instalmentId)
      ->condition('project_nid', $projectId)
      ->execute() > 0;
  }

  public function saveCommercialSchedule(int $projectId, array $fields, int $now, int $actorUid): void {
    $exists = (bool) $this->database->select('brebo_project_commercial_instalment_schedule', 's')
      ->condition('project_nid', $projectId)
      ->countQuery()
      ->execute()
      ->fetchField();
    if ($exists) {
      $this->database->update('brebo_project_commercial_instalment_schedule')
        ->fields($fields)
        ->condition('project_nid', $projectId)
        ->execute();
      return;
    }
    $this->database->insert('brebo_project_commercial_instalment_schedule')
      ->fields($fields + [
        'project_nid' => $projectId,
        'created' => $now,
        'created_by' => $actorUid,
      ])
      ->execute();
  }

}
