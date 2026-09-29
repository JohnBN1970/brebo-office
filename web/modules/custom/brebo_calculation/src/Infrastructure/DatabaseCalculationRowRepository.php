<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationRowRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationRowRepository implements CalculationRowRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function versionState(int $calculationId, string $version): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['locked_at', 'status'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function row(int $calculationId, string $version, int $rowId): ?array {
    $row = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    return $row ?: NULL;
  }

  public function structureNode(int $calculationId, string $version, string $nodeKey): ?array {
    $node = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s', ['node_key', 'node_type'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $nodeKey)
      ->execute()->fetchAssoc();
    return $node ?: NULL;
  }

  public function structureChildCount(int $calculationId, string $version, string $parentKey): int {
    return (int) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('parent_key', $parentKey)
      ->countQuery()->execute()->fetchField();
  }

  public function nextSortOrder(int $calculationId, string $version, string $paragraphKey): int {
    $query = $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('paragraph_key', $paragraphKey);
    $query->addExpression('MAX(sort_order)', 'max_sort_order');
    return ((int) $query->execute()->fetchField()) + 10;
  }

  public function insert(array $values): void {
    $this->database->insert('brebo_calculation_row_domain')->fields($values)->execute();
  }

  public function update(int $calculationId, string $version, int $rowId, array $values): void {
    $this->database->update('brebo_calculation_row_domain')
      ->fields($values)
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute();
  }

  public function delete(int $calculationId, string $version, int $rowId): void {
    $this->database->delete('brebo_calculation_row_domain')
      ->condition('row_id', $rowId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute();
  }

}
