<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Infrastructure;

use Drupal\brebo_control\Contract\ControlHistoryRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseControlHistoryRepository implements ControlHistoryRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function latestCapturedAt(int $projectId): ?int {
    if (!$this->exists()) {
      return NULL;
    }
    $value = $this->database->select('brebo_control_snapshot', 's')
      ->fields('s', ['captured_at'])
      ->condition('project_nid', $projectId)
      ->orderBy('captured_at', 'DESC')
      ->range(0, 1)
      ->execute()->fetchField();
    return $value === FALSE ? NULL : (int) $value;
  }

  public function append(int $projectId, int $capturedAt, array $fields): void {
    $this->database->insert('brebo_control_snapshot')->fields($fields + [
      'project_nid' => $projectId,
      'captured_at' => $capturedAt,
    ])->execute();
  }

  public function snapshots(int $projectId, int $limit): array {
    if (!$this->exists()) {
      return [];
    }
    $rows = $this->database->select('brebo_control_snapshot', 's')->fields('s')
      ->condition('project_nid', $projectId)
      ->orderBy('captured_at', 'DESC')
      ->range(0, max(2, $limit))
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_reverse($rows);
  }

  private function exists(): bool {
    return $this->database->schema()->tableExists('brebo_control_snapshot');
  }

}
