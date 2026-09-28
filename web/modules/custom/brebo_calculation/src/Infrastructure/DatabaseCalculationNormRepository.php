<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationNormRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationNormRepository implements CalculationNormRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function activeNorms(string $domain, string $normKey): array {
    if (!$this->database->schema()->tableExists('brebo_calculation_norm')) {
      return [];
    }
    return array_values($this->database->select('brebo_calculation_norm', 'n')
      ->fields('n')
      ->condition('domain', $domain)
      ->condition('norm_key', $normKey)
      ->condition('active', 1)
      ->orderBy('priority', 'DESC')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC));
  }
}
