<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationLineReadModelInterface;
use Drupal\Core\Database\Connection;

/**
 * BREBO-owned calculation line read model backed by row-domain storage.
 */
final class DatabaseCalculationLineReadModel implements CalculationLineReadModelInterface {

  public function __construct(
    private readonly Connection $database,
  ) {}

  public function loadMany(array $rowIds, string $version): array {
    $rowIds = array_values(array_unique(array_filter(array_map('intval', $rowIds), static fn (int $id): bool => $id > 0)));
    $version = trim($version);
    if ($rowIds === [] || $version === '') {
      return [];
    }

    $rows = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', [
        'row_id',
        'description',
        'contract_quantity',
        'actual_quantity',
        'unit',
        'budget_hours',
        'labour_rate',
      ])
      ->condition('row_id', $rowIds, 'IN')
      ->condition('version', $version)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
      $id = (int) $row['row_id'];
      $result[$id] = [
        'description' => (string) ($row['description'] ?? ''),
        'contract_quantity' => (float) ($row['contract_quantity'] ?? 0),
        'actual_quantity' => $row['actual_quantity'] === NULL ? NULL : (float) $row['actual_quantity'],
        'unit' => (string) ($row['unit'] ?? ''),
        'budget_hours' => (float) ($row['budget_hours'] ?? 0),
        'labour_rate' => (float) ($row['labour_rate'] ?? 0),
      ];
    }
    return $result;
  }

}
