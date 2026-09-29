<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** Stores and reads approved, traceable kozijn price observations. */
final class KozijnPriceObservationRepository implements KozijnPriceObservationProviderInterface {

  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $values */
  public function add(array $values, ?int $userId = NULL): int {
    foreach (['system', 'width_mm', 'height_mm', 'configuration_type', 'supplier_gross', 'observed_at'] as $required) {
      if (!array_key_exists($required, $values)) {
        throw new \InvalidArgumentException('Missing required observation field: ' . $required);
      }
    }
    $system = trim((string) $values['system']);
    $configurationType = trim((string) $values['configuration_type']);
    $integerSyntax = static fn (mixed $value): bool => is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1);
    $decimalSyntax = static fn (mixed $value): bool => (is_int($value) || is_string($value)) && preg_match('/^(?:0|[1-9][0-9]{0,13})(?:\\.[0-9]{1,4})?$/', (string) $value) === 1;
    $fieldsValue = $values['fields_count'] ?? 1;
    if (!$integerSyntax($values['width_mm']) || !$integerSyntax($values['height_mm']) || !$integerSyntax($values['observed_at']) || !$integerSyntax($fieldsValue) || !$decimalSyntax($values['supplier_gross'])) {
      throw new \InvalidArgumentException('Observation numeric values must use canonical numeric syntax.');
    }
    $width = (int) $values['width_mm'];
    $height = (int) $values['height_mm'];
    $gross = (string) $values['supplier_gross'];
    $observedAt = (int) $values['observed_at'];
    $fieldsCount = (int) $fieldsValue;
    if ($system === '' || $configurationType === '' || $width < 1 || $height < 1 || $fieldsCount < 1 || (float) $gross <= 0 || $observedAt < 1) {
      throw new \InvalidArgumentException('Invalid kozijn price observation.');
    }

    return (int) $this->database->insert('brebo_kozijn_price_observation')->fields([
      'system' => $system,
      'brand' => $this->nullable($values['brand'] ?? NULL),
      'width_mm' => $width,
      'height_mm' => $height,
      'fields_count' => $fieldsCount,
      'configuration_type' => $configurationType,
      'glass_spec' => $this->nullable($values['glass_spec'] ?? NULL),
      'colour' => $this->nullable($values['colour'] ?? NULL),
      'joint_type' => $this->nullable($values['joint_type'] ?? NULL),
      'rebate_type' => $this->nullable($values['rebate_type'] ?? NULL),
      'supplier_gross' => $gross,
      'currency' => strtoupper(trim((string) ($values['currency'] ?? 'EUR'))),
      'source_type' => trim((string) ($values['source_type'] ?? 'controlled_quote')),
      'source_ref' => $this->nullable($values['source_ref'] ?? NULL),
      'observed_at' => $observedAt,
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
      ->condition('currency', 'EUR')
      ->orderBy('observed_at', 'DESC')
      ->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  private function nullable(mixed $value): ?string {
    $value = trim((string) ($value ?? ''));
    return $value === '' ? NULL : $value;
  }

}
