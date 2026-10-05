<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Infrastructure;

use Drupal\brebo_glass\Contract\GlassCalculationLinkRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseGlassCalculationLinkRepository implements GlassCalculationLinkRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function isAvailable(): bool {
    return $this->database->schema()->tableExists('brebo_calculation_row_domain');
  }

  public function countLinks(int $positionId, int $calculationId, string $version): int {
    if (!$this->isAvailable()) {
      return 0;
    }

    return (int) $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('source_domain', 'brebo_glass_position')
      ->condition('source_reference', (string) $positionId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function links(int $positionId, int $calculationId, string $version): array {
    if (!$this->isAvailable()) {
      return [];
    }

    $rows = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['row_id', 'source_checksum'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('source_domain', 'brebo_glass_position')
      ->condition('source_reference', (string) $positionId)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(
      static fn(array $row): array => [
        'row_id' => (int) ($row['row_id'] ?? 0),
        'source_checksum' => (string) ($row['source_checksum'] ?? ''),
      ],
      $rows,
    );
  }

}
