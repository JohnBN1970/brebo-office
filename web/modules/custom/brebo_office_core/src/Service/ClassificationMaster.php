<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Database\Connection;

/** Central classification master for NL/SfB, STABU and BREBO codes. */
final class ClassificationMaster {

  public const SYSTEM_NLSFB = 'nlsfb';
  public const SYSTEM_STABU = 'stabu';
  public const SYSTEM_BREBO = 'brebo';

  public function __construct(private readonly Connection $database) {}

  /** Returns active classification entries for a system. */
  public function options(string $system, ?string $parentCode = NULL): array {
    if (!$this->database->schema()->tableExists('brebo_classification')) {
      return [];
    }
    $query = $this->database->select('brebo_classification', 'c')
      ->fields('c', ['id', 'system', 'code', 'description', 'parent_code', 'level', 'source', 'source_version'])
      ->condition('system', $system)
      ->condition('active', 1)
      ->orderBy('sort_order')
      ->orderBy('code');
    if ($parentCode !== NULL) {
      $query->condition('parent_code', $parentCode);
    }
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** Returns one active entry by system and code. */
  public function find(string $system, string $code): ?array {
    if (!$this->database->schema()->tableExists('brebo_classification')) {
      return NULL;
    }
    $row = $this->database->select('brebo_classification', 'c')
      ->fields('c')
      ->condition('system', $system)
      ->condition('code', $code)
      ->condition('active', 1)
      ->range(0, 1)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  /** Stores or updates centrally managed classification data. */
  public function upsert(string $system, string $code, string $description, ?string $parentCode = NULL, int $level = 1, ?string $source = NULL, ?string $sourceVersion = NULL, int $sortOrder = 0): void {
    $system = strtolower(trim($system));
    if (!in_array($system, [self::SYSTEM_NLSFB, self::SYSTEM_STABU, self::SYSTEM_BREBO], TRUE)) {
      throw new \InvalidArgumentException('Unsupported classification system.');
    }
    $code = trim($code);
    $description = trim($description);
    if ($code === '' || $description === '') {
      throw new \InvalidArgumentException('Classification code and description are required.');
    }
    $this->database->merge('brebo_classification')
      ->keys(['system' => $system, 'code' => $code])
      ->fields([
        'description' => $description,
        'parent_code' => $parentCode !== NULL && trim($parentCode) !== '' ? trim($parentCode) : NULL,
        'level' => $level,
        'active' => 1,
        'source' => $source,
        'source_version' => $sourceVersion,
        'sort_order' => $sortOrder,
        'changed' => time(),
      ])
      ->execute();
  }

  /** Returns mappings from one classification entry to other systems. */
  public function relations(string $system, string $code): array {
    if (!$this->database->schema()->tableExists('brebo_classification_relation')) {
      return [];
    }
    return $this->database->select('brebo_classification_relation', 'r')
      ->fields('r')
      ->condition('source_system', $system)
      ->condition('source_code', $code)
      ->orderBy('target_system')
      ->orderBy('target_code')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  /** Creates or updates a cross-system relationship. */
  public function relate(string $sourceSystem, string $sourceCode, string $targetSystem, string $targetCode, string $relationType = 'related', ?string $note = NULL): void {
    if ($sourceSystem === $targetSystem && $sourceCode === $targetCode) {
      throw new \InvalidArgumentException('A classification entry cannot relate to itself.');
    }
    $this->database->merge('brebo_classification_relation')
      ->keys([
        'source_system' => $sourceSystem,
        'source_code' => $sourceCode,
        'target_system' => $targetSystem,
        'target_code' => $targetCode,
      ])
      ->fields(['relation_type' => $relationType, 'note' => $note, 'active' => 1, 'changed' => time()])
      ->execute();
  }
}
