<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Stores and reads approved, traceable kozijn price observations. */
final class KozijnPriceObservationRepository {

  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $values */
  public function add(array $values, ?int $userId = NULL): int {
    foreach (['system', 'width_mm', 'height_mm', 'configuration_type', 'supplier_gross', 'observed_at'] as $required) {
      if (!isset($values[$required]) || $values[$required] === '') {
        throw new \InvalidArgumentException('Missing required observation field: ' . $required);
      }
    }
    $width = (int) $values['width_mm'];
    $height = (int) $values['height_mm'];
    $gross = (float) $values['supplier_gross'];
    if ($width < 1 || $height < 1 || !is_finite($gross) || $gross <= 0) {
      throw new \InvalidArgumentException('Invalid kozijn price observation.');
    }

    return (int) $this->database->insert('brebo_kozijn_price_observation')->fields([
      'system' => trim((string) $values['system']),
      'brand' => $this->nullable($values['brand'] ?? NULL),
      'width_mm' => $width,
      'height_mm' => $height,
      'fields_count' => max(1, (int) ($values['fields_count'] ?? 1)),
      'configuration_type' => trim((string) $values['configuration_type']),
      'glass_spec' => $this->nullable($values['glass_spec'] ?? NULL),
      'colour' => $this->nullable($values['colour'] ?? NULL),
      'joint_type' => $this->nullable($values['joint_type'] ?? NULL),
      'rebate_type' => $this->nullable($values['rebate_type'] ?? NULL),
      'supplier_gross' => $gross,
      'currency' => strtoupper(trim((string) ($values['currency'] ?? 'EUR'))),
      'source_type' => trim((string) ($values['source_type'] ?? 'controlled_quote')),
      'source_ref' => $this->nullable($values['source_ref'] ?? NULL),
      'observed_at' => (int) $values['observed_at'],
      'status' => (string) ($values['status'] ?? 'review'),
      'metadata' => isset($values['metadata']) ? json_encode($values['metadata'], JSON_THROW_ON_ERROR) : NULL,
      'created' => time(),
      'created_by' => $userId,
    ])->execute();
  }

  /** @return array<int,array<string,mixed>> */
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

  private function nullable(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? NULL : $value;
  }

}
