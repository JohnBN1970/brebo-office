<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\LegacyMigrationRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseLegacyMigrationRepository implements LegacyMigrationRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function versionExists(int $calculationId, string $version): bool {
    return (bool) $this->database->select('brebo_calculation_version', 'v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function transactional(callable $callback): void {
    $transaction = $this->database->startTransaction();
    try {
      $callback();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function assertWritten(
    int $calculationId,
    string $version,
    int $structureCount,
    int $rowCount,
    string $hash,
  ): void {
    $storedStructure = (int) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->countQuery()
      ->execute()
      ->fetchField();

    $storedRows = (int) $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->countQuery()
      ->execute()
      ->fetchField();

    $storedHash = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['content_hash'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchField();

    if ($storedStructure !== $structureCount || $storedRows !== $rowCount || $storedHash !== $hash) {
      throw new \RuntimeException('Calculation migration verification failed; transaction will be rolled back.');
    }
  }
}
