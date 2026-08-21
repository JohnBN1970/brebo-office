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
}
