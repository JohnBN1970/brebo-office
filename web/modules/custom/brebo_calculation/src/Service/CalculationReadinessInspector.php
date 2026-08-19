<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Aggregates calculation quality checks into an offer-readiness status. */
final class CalculationReadinessInspector {

  public function __construct(
    private readonly Connection $database,
    private readonly RecipePriceHealthInspector $priceHealthInspector,
  ) {}

  /**
   * @return array{status:string,blocking:int,warnings:int,checks:array<int,array<string,mixed>>}
   */
  public function inspect(int $calculationId, string $version): array {
    $checks = [];
    $blocking = 0;
    $warnings = 0;

    $rows = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      $quantity = (float) ($row['quantity'] ?? 0);
      $unitCost = (float) ($row['labour_unit_cost'] ?? 0)
        + (float) ($row['material_unit_cost'] ?? 0)
        + (float) ($row['equipment_unit_cost'] ?? 0)
        + (float) ($row['subcontracting_unit_cost'] ?? 0)
        + (float) ($row['other_unit_cost'] ?? 0);
      if ($quantity <= 0) {
        $checks[] = ['level' => 'warning', 'code' => 'row_zero_quantity', 'label' => 'Losse regel zonder hoeveelheid', 'reference' => (int) ($row['calc_line_id'] ?? 0)];
        $warnings++;
      }
      if ($unitCost <= 0) {
        $checks[] = ['level' => 'warning', 'code' => 'row_zero_cost', 'label' => 'Losse regel zonder kostprijs', 'reference' => (int) ($row['calc_line_id'] ?? 0)];
        $warnings++;
      }
    }

    $instanceLines = $this->database->select('brebo_calculation_recipe_instance_line', 'l');
    $instanceLines->join('brebo_calculation_recipe_instance', 'i', 'i.id = l.recipe_instance_id');
    $instanceLines->fields('l');
    $instanceLines->condition('i.calculation_id', $calculationId);
    $instanceLines->condition('i.calculation_version', $version);
    $recipeLines = $instanceLines->execute()->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($recipeLines as $line) {
      $health = $this->priceHealthInspector->inspect($line);
      if ($health['level'] === 'error') {
        $blocking++;
        $checks[] = ['level' => 'error', 'code' => $health['code'], 'label' => $health['label'], 'reference' => (int) ($line['id'] ?? 0)];
      }
      elseif ($health['level'] === 'warning') {
        $warnings++;
        $checks[] = ['level' => 'warning', 'code' => $health['code'], 'label' => $health['label'], 'reference' => (int) ($line['id'] ?? 0)];
      }

      if ($line['manual_quantity'] !== NULL && $line['manual_quantity'] !== '') {
        $warnings++;
        $checks[] = ['level' => 'warning', 'code' => 'manual_quantity_override', 'label' => 'Handmatige hoeveelheidsafwijking', 'reference' => (int) ($line['id'] ?? 0)];
      }
    }

    $status = $blocking > 0 ? 'blocked' : ($warnings > 0 ? 'review' : 'ready');
    return ['status' => $status, 'blocking' => $blocking, 'warnings' => $warnings, 'checks' => $checks];
  }
}
