<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ClosedLoopControlRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for closed-loop control persistence. */
final class DatabaseClosedLoopControlRepository implements ClosedLoopControlRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @return array<int, array<string, mixed>> */
  public function findResolvedActionsBefore(int $cutoff): array {
    return $this->database
      ->select('brebo_management_action', 'a')
      ->fields('a')
      ->condition('status', 'resolved')
      ->condition('resolved_at', 0, '>')
      ->condition('resolved_at', $cutoff, '<=')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @param array<string, mixed> $fields */
  public function updateAction(int $actionId, array $fields): void {
    $this->database
      ->update('brebo_management_action')
      ->fields($fields)
      ->condition('id', $actionId)
      ->execute();
  }

}
