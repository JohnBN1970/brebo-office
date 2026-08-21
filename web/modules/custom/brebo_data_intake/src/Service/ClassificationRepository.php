<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

final class ClassificationRepository {
  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  public function upsert(string $systemKey, string $sourceVersion, string $code, string $label, ?string $parentCode = NULL, int $level = 0, ?int $sourceRecordId = NULL): int {
    foreach ([$systemKey, $sourceVersion, $code, $label] as $required) {
      if (trim($required) === '') {
        throw new \InvalidArgumentException('Classification term is incomplete.');
      }
    }
    $now = $this->time->getRequestTime();
    $this->database->merge('brebo_classification_term')
      ->keys(['system_key' => $systemKey, 'source_version' => $sourceVersion, 'code' => $code])
      ->fields([
        'label' => mb_substr($label, 0, 512),
        'parent_code' => $parentCode !== NULL ? mb_substr(trim($parentCode), 0, 64) : NULL,
        'level' => max(0, min(255, $level)),
        'active' => 1,
        'source_record_id' => $sourceRecordId,
        'changed' => $now,
      ])
      ->insertFields(['created' => $now])
      ->execute();
    return (int) $this->database->select('brebo_classification_term', 't')
      ->fields('t', ['id'])
      ->condition('system_key', $systemKey)
      ->condition('source_version', $sourceVersion)
      ->condition('code', $code)
      ->execute()->fetchField();
  }

  public function allForVersion(string $systemKey, string $sourceVersion): array {
    return $this->database->select('brebo_classification_term', 't')
      ->fields('t')
      ->condition('system_key', $systemKey)
      ->condition('source_version', $sourceVersion)
      ->condition('active', 1)
      ->orderBy('code')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** @return string[] */
  public function versions(string $systemKey): array {
    $query = $this->database->select('brebo_classification_term', 't');
    $query->addField('t', 'source_version');
    $query->condition('system_key', $systemKey)
      ->condition('active', 1)
      ->distinct()
      ->orderBy('source_version', 'DESC');
    return array_values(array_map('strval', $query->execute()->fetchCol()));
  }

  public function findByCode(string $systemKey, string $sourceVersion, string $code): ?array {
    $row = $this->database->select('brebo_classification_term', 't')
      ->fields('t')
      ->condition('system_key', $systemKey)
      ->condition('source_version', $sourceVersion)
      ->condition('code', trim($code))
      ->condition('active', 1)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function search(string $systemKey, string $sourceVersion, string $search = '', int $limit = 50): array {
    $query = $this->database->select('brebo_classification_term', 't')
      ->fields('t')
      ->condition('system_key', $systemKey)
      ->condition('source_version', $sourceVersion)
      ->condition('active', 1);

    $search = trim($search);
    if ($search !== '') {
      $group = $query->orConditionGroup()
        ->condition('code', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
        ->condition('label', '%' . $this->database->escapeLike($search) . '%', 'LIKE');
      $query->condition($group);
    }

    return $query
      ->orderBy('code')
      ->range(0, max(1, min(250, $limit)))
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
}
