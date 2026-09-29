<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationLineReadModelInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Transitional Drupal adapter exposing legacy calculation lines as plain data.
 */
final class DrupalCalculationLineReadModel implements CalculationLineReadModelInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function loadMany(array $lineIds, string $version): array {
    $lineIds = array_values(array_unique(array_map('intval', $lineIds)));
    if ($lineIds === []) {
      return [];
    }

    $entities = $this->entityTypeManager->getStorage('node')->loadMultiple($lineIds);
    $result = [];

    foreach ($entities as $id => $line) {
      if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line') {
        continue;
      }

      $actualRaw = $line->hasField('field_brebo_actual_quantity')
        ? $line->get('field_brebo_actual_quantity')->value
        : NULL;

      $result[(int) $id] = [
        'description' => (string) ($line->get('field_brebo_line_description')->value ?? $line->label()),
        'contract_quantity' => $line->hasField('field_brebo_contract_quantity')
          ? (float) ($line->get('field_brebo_contract_quantity')->value ?? 0)
          : 0.0,
        'actual_quantity' => $actualRaw === NULL || $actualRaw === '' ? NULL : (float) $actualRaw,
        'unit' => (string) ($line->get('field_brebo_unit')->value ?? ''),
        'budget_hours' => $line->hasField('field_brebo_budget_hours')
          ? (float) ($line->get('field_brebo_budget_hours')->value ?? 0)
          : 0.0,
        'labour_rate' => $line->hasField('field_brebo_labor_rate')
          ? (float) ($line->get('field_brebo_labor_rate')->value ?? 0)
          : 0.0,
      ];
    }

    return $result;
  }

}
