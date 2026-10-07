<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ManagementActionRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for management action persistence. */
final class DatabaseManagementActionRepository implements ManagementActionRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function insert(array $record): int {
    return (int) $this->database
      ->insert('brebo_management_action')
      ->fields($record)
      ->execute();
  }

  public function resolveOpenAction(int $actionId, array $fields): void {
    $this->database
      ->update('brebo_management_action')
      ->fields($fields)
      ->condition('id', $actionId)
      ->condition('status', 'open')
      ->execute();
  }

  public function findOpenActions(?string $actionKey = NULL): array {
    $query = $this->database
      ->select('brebo_management_action', 'a')
      ->fields('a')
      ->condition('status', ['open', 'reopened'], 'IN')
      ->orderBy('due_at', 'ASC');

    if ($actionKey !== NULL && $actionKey !== '') {
      $query->condition('action_key', $actionKey);
    }

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function findOverdueActions(int $now): array {
    return $this->database
      ->select('brebo_management_action', 'a')
      ->fields('a')
      ->condition('status', ['open', 'reopened'], 'IN')
      ->condition('due_at', $now, '<')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function hasOpenAction(string $actionKey): bool {
    return (bool) $this->database
      ->select('brebo_management_action', 'a')
      ->condition('action_key', $actionKey)
      ->condition('status', ['open', 'reopened'], 'IN')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
