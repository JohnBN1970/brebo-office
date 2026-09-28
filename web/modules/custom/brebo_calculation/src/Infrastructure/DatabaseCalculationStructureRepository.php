<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationStructureRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationStructureRepository implements CalculationStructureRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function versionState(int $calculationId, string $version): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function node(int $calculationId, string $version, string $nodeKey): ?array {
    $row = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $nodeKey)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function nextSortOrder(int $calculationId, string $version, ?string $parentKey): int {
    $query = $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version);
    if ($parentKey === NULL) {
      $query->isNull('parent_key');
    } else {
      $query->condition('parent_key', $parentKey);
    }
    $query->addExpression('MAX(sort_order)', 'max_sort_order');
    return ((int) $query->execute()->fetchField()) + 10;
  }

  public function insert(array $values): void {
    $this->database->insert('brebo_calculation_structure')->fields($values)->execute();
  }

  public function reorder(int $calculationId, string $version, string $nodeKey, int $sortOrder): void {
    $this->database->update('brebo_calculation_structure')
      ->fields(['sort_order' => $sortOrder])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $nodeKey)
      ->execute();
  }

}
