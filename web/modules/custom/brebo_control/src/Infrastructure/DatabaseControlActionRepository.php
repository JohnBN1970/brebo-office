<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Infrastructure;

use Drupal\brebo_control\Contract\ControlActionRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseControlActionRepository implements ControlActionRepositoryInterface {

  private const ACTIVE = ['open', 'reopened', 'in_progress', 'escalated'];

  public function __construct(private readonly Connection $database) {}

  public function activeRows(): array {
    if (!$this->exists()) {
      return [];
    }
    return $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('status', self::ACTIVE, 'IN')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function projectActions(int $projectId): array {
    if (!$this->exists()) {
      return [];
    }
    return $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('project_nid', $projectId)
      ->orderBy('risk_points', 'DESC')
      ->orderBy('due_at', 'ASC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function byProjectDriver(int $projectId, string $driverCode): ?array {
    if (!$this->exists()) {
      return NULL;
    }
    $row = $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('project_nid', $projectId)
      ->condition('driver_code', $driverCode)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function byId(int $actionId): ?array {
    if (!$this->exists()) {
      return NULL;
    }
    $row = $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('id', $actionId)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function create(int $projectId, string $driverCode, array $fields): int {
    return (int) $this->database->insert('brebo_control_action')->fields($fields + [
      'project_nid' => $projectId,
      'driver_code' => $driverCode,
    ])->execute();
  }

  public function update(int $actionId, array $fields): void {
    $this->database->update('brebo_control_action')
      ->fields($fields)
      ->condition('id', $actionId)
      ->execute();
  }

  public function overdueRows(int $now): array {
    if (!$this->exists()) {
      return [];
    }
    return $this->database->select('brebo_control_action', 'a')->fields('a')
      ->condition('status', self::ACTIVE, 'IN')
      ->condition('due_at', 0, '>')
      ->condition('due_at', $now, '<')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function countOpenForProject(int $projectId): int {
    if (!$this->exists()) {
      return 0;
    }
    return (int) $this->database->select('brebo_control_action', 'a')
      ->condition('project_nid', $projectId)
      ->condition('status', self::ACTIVE, 'IN')
      ->countQuery()->execute()->fetchField();
  }

  public function recurringDrivers(int $minimumProjects = 2): array {
    if (!$this->exists()) {
      return [];
    }
    $query = $this->database->select('brebo_control_action', 'a');
    $query->addField('a', 'driver_code');
    $query->addExpression('COUNT(DISTINCT project_nid)', 'project_count');
    $query->condition('status', self::ACTIVE, 'IN');
    $query->groupBy('driver_code');
    $query->having('COUNT(DISTINCT project_nid) >= :minimum', [':minimum' => max(1, $minimumProjects)]);
    return array_map(
      static fn(array $row): array => [
        'driver_code' => (string) $row['driver_code'],
        'project_count' => (int) $row['project_count'],
      ],
      $query->execute()->fetchAll(\PDO::FETCH_ASSOC),
    );
  }

  private function exists(): bool {
    return $this->database->schema()->tableExists('brebo_control_action');
  }

}
