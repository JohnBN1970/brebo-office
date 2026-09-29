<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationReadinessRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationLineReadModelInterface;

/** Aggregates calculation quality checks into an offer-readiness status. */
final class CalculationReadinessInspector {

  public function __construct(
    private readonly CalculationReadinessRepositoryInterface $repository,
    private readonly RecipePriceHealthInspector $priceHealthInspector,
    private readonly CalculationLineReadModelInterface $lineReadModel,
  ) {}

  /**
   * @return array{status:string,blocking:int,warnings:int,checks:array<int,array<string,mixed>>}
   */
  public function inspect(int $calculationId, string $version): array {
    $checks = [];
    $blocking = 0;
    $warnings = 0;

    $rows = $this->repository->rows($calculationId, $version);

    $rowIds = array_values(array_filter(array_map(
      static fn (array $row): int => (int) ($row['row_id'] ?? 0),
      $rows,
    )));
    $rowData = $rowIds ? $this->lineReadModel->loadMany($rowIds, $version) : [];

    foreach ($rows as $row) {
      $rowId = (int) ($row['row_id'] ?? 0);
      $line = $rowData[$rowId] ?? NULL;
      $quantity = is_array($line) ? (float) ($line['contract_quantity'] ?? 0) : 0.0;
      $unitCost = (float) ($row['labour_unit_cost'] ?? 0)
        + (float) ($row['material_unit_cost'] ?? 0)
        + (float) ($row['equipment_unit_cost'] ?? 0)
        + (float) ($row['subcontracting_unit_cost'] ?? 0)
        + (float) ($row['other_unit_cost'] ?? 0);
      if ($quantity <= 0) {
        $checks[] = ['level' => 'warning', 'code' => 'row_zero_quantity', 'label' => 'Losse regel zonder hoeveelheid', 'reference' => (int) ($row['row_id'] ?? 0)];
        $warnings++;
      }
      if ($unitCost <= 0) {
        $checks[] = ['level' => 'warning', 'code' => 'row_zero_cost', 'label' => 'Losse regel zonder kostprijs', 'reference' => (int) ($row['row_id'] ?? 0)];
        $warnings++;
      }
    }

    $recipeLines = $this->repository->recipeLines($calculationId, $version);

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
