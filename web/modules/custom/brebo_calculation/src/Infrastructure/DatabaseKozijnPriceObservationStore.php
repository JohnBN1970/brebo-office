<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\KozijnPriceObservationStoreInterface;
use Drupal\Core\Database\Connection;

final class DatabaseKozijnPriceObservationStore implements KozijnPriceObservationStoreInterface {

  public function __construct(private readonly Connection $database) {}

  public function insert(array $values): int {
    return (int) $this->database->insert('brebo_kozijn_price_observation')->fields($values)->execute();
  }

  public function approved(string $system, string $type, int $fields = 1): array {
    return $this->database->select('brebo_kozijn_price_observation', 'o')
      ->fields('o')
      ->condition('system', $system)
      ->condition('configuration_type', $type)
      ->condition('fields_count', $fields)
      ->condition('status', 'approved')
      ->orderBy('observed_at', 'DESC')
      ->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }
}
