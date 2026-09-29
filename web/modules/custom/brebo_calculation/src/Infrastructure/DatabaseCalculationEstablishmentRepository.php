<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationEstablishmentRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationEstablishmentRepository implements CalculationEstablishmentRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function version(int $calculationId, string $version): ?array {
    $row = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function structure(int $calculationId, string $version): array {
    return $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function rowDomain(int $rowId, string $version): ?array {
    $row = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r')
      ->condition('row_id', $rowId)
      ->condition('version', $version)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function snapshotExists(int $calculationId, string $version): bool {
    return (bool) $this->database->select('brebo_calculation_snapshot', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function persistSnapshotAndLock(
    int $calculationId,
    string $version,
    array $snapshot,
    array $lock,
  ): void {
    $transaction = $this->database->startTransaction();
    try {
      if ($this->snapshotExists($calculationId, $version)) {
        throw new \RuntimeException('Voor deze calculatieversie bestaat al een immutable snapshot.');
      }

      $this->database->insert('brebo_calculation_snapshot')
        ->fields($snapshot)
        ->execute();

      $updated = $this->database->update('brebo_calculation_version')
        ->fields($lock)
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('status', 'draft')
        ->isNull('locked_at')
        ->execute();

      if ($updated !== 1) {
        throw new \RuntimeException('Calculatieversie veranderde tijdens het vaststellen.');
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }
}
