<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationAccessRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseCalculationAccessRepository implements CalculationAccessRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function calculationExists(int $calculationId): bool {
    return (bool) $this->database->select('brebo_calculation_version', 'v')
      ->condition('calculation_id', $calculationId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function latestEstablishedVersion(int $calculationId): ?string {
    $version = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version'])
      ->condition('calculation_id', $calculationId)
      ->condition('status', 'established')
      ->isNotNull('locked_at')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return is_string($version) && $version !== '' ? $version : NULL;
  }
}
