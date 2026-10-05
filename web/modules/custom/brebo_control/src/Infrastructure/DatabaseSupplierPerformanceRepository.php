<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Infrastructure;

use Drupal\brebo_control\Contract\SupplierPerformanceRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseSupplierPerformanceRepository implements SupplierPerformanceRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function create(array $fields): int {
    return (int) $this->database->insert('brebo_supplier_performance_event')
      ->fields($fields)
      ->execute();
  }

  public function eventsForSupplier(string $supplierName): array {
    if (!$this->database->schema()->tableExists('brebo_supplier_performance_event')) {
      return [];
    }
    return $this->database->select('brebo_supplier_performance_event', 'e')->fields('e')
      ->condition('supplier_name', $supplierName)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
