<?php

declare(strict_types=1);

namespace Drupal\brebo_measure\Infrastructure;

use Drupal\brebo_measure\Contract\MeasureStorageInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

final class DatabaseMeasureStorage implements MeasureStorageInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public function currentTime(): int {
    return $this->time->getRequestTime();
  }

  public function insert(string $table, array $values): int {
    return (int) $this->database
      ->insert($table)
      ->fields($values)
      ->execute();
  }

  public function load(string $table, int $id): ?array {
    $row = $this->database
      ->select($table, 'x')
      ->fields('x')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    return $row === FALSE ? NULL : $row;
  }

  public function maxVersionForAssignment(int $assignmentId): int {
    $query = $this->database->select('brebo_measure_capture', 'c');
    $query->addExpression('MAX(version)', 'max_version');
    return (int) $query
      ->condition('assignment_id', $assignmentId)
      ->execute()
      ->fetchField();
  }

}
