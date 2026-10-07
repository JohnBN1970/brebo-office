<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ControllerCaseRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for controller-case persistence. */
final class DatabaseControllerCaseRepository implements ControllerCaseRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @param array<string, mixed> $record */
  public function insert(array $record): int {
    return (int) $this->database
      ->insert('brebo_controller_case')
      ->fields($record)
      ->execute();
  }

  /** @return array<string, mixed>|null */
  public function findById(int $caseId): ?array {
    $row = $this->database
      ->select('brebo_controller_case', 'c')
      ->fields('c')
      ->condition('id', $caseId)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  /** @param array<string, mixed> $fields */
  public function update(int $caseId, array $fields): void {
    $this->database
      ->update('brebo_controller_case')
      ->fields($fields)
      ->condition('id', $caseId)
      ->execute();
  }

}
