<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Stores verified product performance and supplies selection candidates.
 */
final class GlassProductRepository {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * @param array<string, mixed> $values
   */
  public function insert(array $values): int {
    $now = $this->time->getRequestTime();
    $values += ['created' => $now, 'changed' => $now];

    return (int) $this->database->insert('brebo_glass_product')
      ->fields($values)
      ->execute();
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function activeVerifiedCandidates(): array {
    return $this->database->select('brebo_glass_product', 'p')
      ->fields('p')
      ->condition('active', 1)
      ->condition('verified', 1)
      ->orderBy('weight_kg_m2', 'ASC')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function findAll(): array {
    return $this->database->select('brebo_glass_product', 'p')
      ->fields('p')
      ->orderBy('active', 'DESC')
      ->orderBy('label', 'ASC')
      ->execute()
      ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

}
