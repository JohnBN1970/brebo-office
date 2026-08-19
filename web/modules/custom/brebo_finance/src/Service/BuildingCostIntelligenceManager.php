<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builds auditable unit-cost intelligence from verified project evidence.
 */
final class BuildingCostIntelligenceManager {

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * Records one immutable, verified unit-cost observation.
   *
   * @param array<string, mixed> $data
   */
  public function recordObservation(array $data, int $actorUid): int {
    foreach (['project_nid', 'cost_code', 'work_type', 'specification_hash', 'unit', 'quantity', 'unit_cost_ex_vat', 'amount_ex_vat', 'source_type', 'source_id', 'source_hash', 'observation_date', 'region'] as $required) {
      if (!isset($data[$required]) || $data[$required] === '') {
        throw new InvalidArgumentException("$required is required.");
      }
    }
    if (!in_array($data['source_type'], ['verified_performance', 'matched_purchase_invoice', 'closed_failure_cost', 'approved_labour_actual'], TRUE)) {
      throw new InvalidArgumentException('Cost observations require a verified financial source.');
    }
    if ($this->decimal->compare((string) $data['quantity'], '0') <= 0 || $this->decimal->compare((string) $data['unit_cost_ex_vat'], '0') < 0) {
      throw new InvalidArgumentException('Quantity must be positive and unit cost cannot be negative.');
    }
    $calculated = $this->decimal->multiply((string) $data['quantity'], (string) $data['unit_cost_ex_vat']);
    if ($this->decimal->compare($calculated, (string) $data['amount_ex_vat']) !== 0) {
      throw new InvalidArgumentException('Quantity multiplied by unit cost must equal the source amount.');
    }
    if (empty($data['quality_accepted'])) {
      throw new RuntimeException('Rejected or unverified quality cannot train the cost library.');
    }
    $evidence = [
      'source_type' => (string) $data['source_type'],
      'source_id' => (string) $data['source_id'],
      'source_hash' => (string) $data['source_hash'],
      'specification_hash' => (string) $data['specification_hash'],
      'quality_accepted' => TRUE,
      'failure_cost_adjustment_ex_vat' => (string) ($data['failure_cost_adjustment_ex_vat'] ?? '0'),
    ];
    $now = time();
    return (int) $this->database->insert('brebo_finance_cost_observation')
      ->fields([
        'project_nid' => (int) $data['project_nid'],
        'building_object_type' => $data['building_object_type'] ?? NULL,
        'building_object_id' => $data['building_object_id'] ?? NULL,
        'cost_code' => (string) $data['cost_code'],
        'work_type' => (string) $data['work_type'],
        'specification_hash' => (string) $data['specification_hash'],
        'region' => (string) $data['region'],
        'supplier_ref' => $data['supplier_ref'] ?? NULL,
        'unit' => (string) $data['unit'],
        'quantity' => (string) $data['quantity'],
        'unit_cost_ex_vat' => (string) $data['unit_cost_ex_vat'],
        'amount_ex_vat' => (string) $data['amount_ex_vat'],
        'failure_cost_adjustment_ex_vat' => (string) ($data['failure_cost_adjustment_ex_vat'] ?? '0'),
        'source_type' => (string) $data['source_type'],
        'source_id' => (string) $data['source_id'],
        'source_hash' => (string) $data['source_hash'],
        'observation_date' => (string) $data['observation_date'],
        'quality_accepted' => 1,
        'evidence_payload' => json_encode($evidence, JSON_THROW_ON_ERROR),
        'evidence_hash' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
        'created' => $now,
        'created_by' => $actorUid,
      ])->execute();
  }

  /**
   * Creates an immutable benchmark for an exactly matching specification.
   *
   * The upper observed median is used for even samples to avoid optimistic
   * under-budgeting. No inflation or regional correction is silently assumed.
   *
   * @return array<string, mixed>
   */
  public function createBenchmark(
    string $costCode,
    string $workType,
    string $specificationHash,
    string $unit,
    string $region,
    string $snapshotDate,
    int $actorUid,
  ): array {
    $rows = $this->database->select('brebo_finance_cost_observation', 'o')
      ->fields('o', ['id', 'project_nid', 'unit_cost_ex_vat', 'quantity', 'observation_date', 'source_hash'])
      ->condition('cost_code', $costCode)
      ->condition('work_type', $workType)
      ->condition('specification_hash', $specificationHash)
      ->condition('unit', $unit)
      ->condition('region', $region)
      ->condition('quality_accepted', 1)
      ->condition('observation_date', $snapshotDate, '<=')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if ($rows === []) {
      throw new RuntimeException('No exactly matching verified cost observations are available.');
    }
    usort($rows, fn(array $a, array $b): int => $this->decimal->compare((string) $a['unit_cost_ex_vat'], (string) $b['unit_cost_ex_vat']));
    $count = count($rows);
    $minimum = (string) $rows[0]['unit_cost_ex_vat'];
    $median = (string) $rows[intdiv($count, 2)]['unit_cost_ex_vat'];
    $maximum = (string) $rows[$count - 1]['unit_cost_ex_vat'];
    $projectCount = count(array_unique(array_column($rows, 'project_nid')));
    $confidence = match (TRUE) {
      $count >= 20 && $projectCount >= 5 => 'A',
      $count >= 10 && $projectCount >= 3 => 'B',
      $count >= 5 && $projectCount >= 2 => 'C',
      $count >= 3 => 'D',
      default => 'E',
    };
    $payload = [
      'cost_code' => $costCode,
      'work_type' => $workType,
      'specification_hash' => $specificationHash,
      'unit' => $unit,
      'region' => $region,
      'sample_count' => $count,
      'project_count' => $projectCount,
      'minimum_unit_cost_ex_vat' => $minimum,
      'benchmark_unit_cost_ex_vat' => $median,
      'maximum_unit_cost_ex_vat' => $maximum,
      'confidence_class' => $confidence,
      'observation_ids' => array_map('intval', array_column($rows, 'id')),
      'source_hashes' => array_values(array_column($rows, 'source_hash')),
      'method' => 'upper_observed_median_exact_specification',
    ];
    $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    $id = (int) $this->database->insert('brebo_finance_cost_benchmark_snapshot')
      ->fields([
        'cost_code' => $costCode,
        'work_type' => $workType,
        'specification_hash' => $specificationHash,
        'unit' => $unit,
        'region' => $region,
        'snapshot_date' => $snapshotDate,
        'sample_count' => $count,
        'project_count' => $projectCount,
        'minimum_unit_cost_ex_vat' => $minimum,
        'benchmark_unit_cost_ex_vat' => $median,
        'maximum_unit_cost_ex_vat' => $maximum,
        'confidence_class' => $confidence,
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'content_hash' => $hash,
        'created' => time(),
        'created_by' => $actorUid,
      ])->execute();
    return ['id' => $id, 'content_hash' => $hash] + $payload;
  }

  /**
   * Compares a candidate only with an exact benchmark match.
   *
   * @return array<string, mixed>
   */
  public function compareCandidate(int $benchmarkId, string $candidateUnitCostExVat): array {
    $row = $this->database->select('brebo_finance_cost_benchmark_snapshot', 'b')
      ->fields('b')
      ->condition('id', $benchmarkId)
      ->execute()->fetchAssoc();
    if ($row === FALSE) {
      throw new RuntimeException('Cost benchmark does not exist.');
    }
    $variance = $this->decimal->subtract($candidateUnitCostExVat, (string) $row['benchmark_unit_cost_ex_vat']);
    return [
      'benchmark_id' => $benchmarkId,
      'candidate_unit_cost_ex_vat' => $candidateUnitCostExVat,
      'benchmark_unit_cost_ex_vat' => $row['benchmark_unit_cost_ex_vat'],
      'variance_ex_vat' => $variance,
      'variance_pct' => $this->decimal->percentage($variance, (string) $row['benchmark_unit_cost_ex_vat']),
      'confidence_class' => $row['confidence_class'],
      'requires_human_review' => TRUE,
      'content_hash' => $row['content_hash'],
    ];
  }

}
