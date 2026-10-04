<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ControlFindingRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for financial control findings. */
final class DatabaseControlFindingRepository implements ControlFindingRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function get(int $findingId): ?array {
    $row = $this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f')
      ->condition('id', $findingId)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function update(int $findingId, array $fields): void {
    $this->database->update('brebo_finance_control_finding')
      ->fields($fields)
      ->condition('id', $findingId)
      ->execute();
  }

  public function appendAudit(array $audit): void {
    $this->database->insert('brebo_finance_audit')->fields($audit)->execute();
  }

}
