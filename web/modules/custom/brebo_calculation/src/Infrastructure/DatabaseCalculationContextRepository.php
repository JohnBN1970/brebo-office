<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationContextRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationContextRepository implements CalculationContextRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function get(int $calculationId): ?array {
    $row = $this->database->select('brebo_calculation_context', 'c')
      ->fields('c')
      ->condition('calculation_id', $calculationId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function upsert(int $calculationId, array $context): void {
    $this->database->merge('brebo_calculation_context')
      ->key(['calculation_id' => $calculationId])
      ->fields($context)
      ->execute();
  }
}
