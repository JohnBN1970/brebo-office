<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\OrganizationalLearningRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for organizational learning persistence. */
final class DatabaseOrganizationalLearningRepository implements OrganizationalLearningRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @param array<string, mixed> $record */
  public function insert(array $record): int {
    return (int) $this->database
      ->insert('brebo_organizational_learning')
      ->fields($record)
      ->execute();
  }

  /** @return array<int, array<string, mixed>> */
  public function findDueForReview(int $now): array {
    return $this->database
      ->select('brebo_organizational_learning', 'l')
      ->fields('l')
      ->condition('status', 'approved')
      ->condition('review_at', $now, '<=')
      ->orderBy('review_at', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return array<int, array<string, mixed>> */
  public function findHistory(string $lessonCode): array {
    return $this->database
      ->select('brebo_organizational_learning', 'l')
      ->fields('l')
      ->condition('lesson_code', $lessonCode)
      ->orderBy('created_at', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
