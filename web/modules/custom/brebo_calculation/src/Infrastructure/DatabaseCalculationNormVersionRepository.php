<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationNormVersionRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationNormVersionRepository implements CalculationNormVersionRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function norm(int $normId): ?array {
    if (!$this->database->schema()->tableExists('brebo_calculation_norm')) {
      return NULL;
    }
    $row = $this->database->select('brebo_calculation_norm', 'n')
      ->fields('n')
      ->condition('id', $normId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function replace(int $normId, array $replacement): int {
    $transaction = $this->database->startTransaction();
    try {
      $this->database->update('brebo_calculation_norm')
        ->fields(['active' => 0, 'changed' => time()])
        ->condition('id', $normId)
        ->execute();
      return (int) $this->database->insert('brebo_calculation_norm')
        ->fields($replacement)
        ->execute();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }
}
