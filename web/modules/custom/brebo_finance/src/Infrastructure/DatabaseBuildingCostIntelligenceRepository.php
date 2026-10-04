<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\BuildingCostIntelligenceRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for verified building cost intelligence. */
final class DatabaseBuildingCostIntelligenceRepository implements BuildingCostIntelligenceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function createObservation(array $fields): int {
    return (int) $this->database->insert('brebo_finance_cost_observation')->fields($fields)->execute();
  }

  public function matchingObservations(
    string $costCode,
    string $workType,
    string $specificationHash,
    string $unit,
    string $region,
    string $snapshotDate,
  ): array {
    return $this->database->select('brebo_finance_cost_observation', 'o')
      ->fields('o', ['id', 'project_nid', 'unit_cost_ex_vat', 'quantity', 'observation_date', 'source_hash'])
      ->condition('cost_code', $costCode)
      ->condition('work_type', $workType)
      ->condition('specification_hash', $specificationHash)
      ->condition('unit', $unit)
      ->condition('region', $region)
      ->condition('quality_accepted', 1)
      ->condition('observation_date', $snapshotDate, '<=')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function createBenchmark(array $fields): int {
    return (int) $this->database->insert('brebo_finance_cost_benchmark_snapshot')->fields($fields)->execute();
  }

  public function benchmark(int $benchmarkId): ?array {
    $row = $this->database->select('brebo_finance_cost_benchmark_snapshot', 'b')
      ->fields('b')->condition('id', $benchmarkId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

}
