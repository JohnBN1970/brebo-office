<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationPriceSourceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationPriceSourceRepository implements CalculationPriceSourceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function isEditableVersion(int $calculationId, string $version): bool {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['status', 'locked_at'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchAssoc();
    return (bool) ($row && $row['status'] === 'draft' && $row['locked_at'] === NULL);
  }

  public function rowExists(int $calculationId, string $version, int $rowId): bool {
    return (bool) $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('row_id', $rowId)
      ->countQuery()->execute()->fetchField();
  }

  public function createSourceWithLine(array $source, array $line): int {
    $transaction = $this->database->startTransaction();
    try {
      $sourceId = (int) $this->database->insert('brebo_calculation_price_source')->fields($source)->execute();
      $line['price_source_id'] = $sourceId;
      $this->database->insert('brebo_calculation_price_source_line')->fields($line)->execute();
      return $sourceId;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function mappingId(int $sourceId, int $calculationId, string $version, int $rowId): ?int {
    $id = $this->database->select('brebo_calculation_price_source_line', 'm')
      ->fields('m', ['id'])
      ->condition('price_source_id', $sourceId)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('row_id', $rowId)
      ->execute()->fetchField();
    return $id ? (int) $id : NULL;
  }

  public function approveSource(
    int $calculationId,
    string $version,
    int $rowId,
    int $sourceId,
    int $mappingId,
    string $costCarrier,
    string $targetField,
    float $unitCost,
    array $approval,
  ): void {
    $transaction = $this->database->startTransaction();
    try {
      $this->database->update('brebo_calculation_price_source_line')
        ->fields(['is_active_source' => 0])
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('row_id', $rowId)
        ->condition('source_line_ref', 'cost_carrier:' . $costCarrier)
        ->execute();

      $this->database->update('brebo_calculation_price_source_line')
        ->fields($approval)
        ->condition('id', $mappingId)
        ->execute();

      $this->database->update('brebo_calculation_row_domain')
        ->fields([$targetField => $unitCost])
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('row_id', $rowId)
        ->execute();

      $this->database->update('brebo_calculation_price_source')
        ->fields([
          'status' => 'accepted',
          'changed' => time(),
          'changed_by' => (int) ($approval['approved_by'] ?? 0),
        ])
        ->condition('id', $sourceId)
        ->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }
}
