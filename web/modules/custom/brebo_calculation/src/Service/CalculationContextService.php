<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/** BREBO-owned calculation identity/context read-write service. */
final class CalculationContextService {

  public function __construct(
    private readonly Connection $database,
  ) {}

  /** @return array<string,mixed>|null */
  public function get(int $calculationId): ?array {
    $row = $this->database->select('brebo_calculation_context', 'c')
      ->fields('c')
      ->condition('calculation_id', $calculationId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  /** @param array<string,mixed> $context */
  public function upsert(int $calculationId, array $context): void {
    if ($calculationId <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }
    $this->database->merge('brebo_calculation_context')
      ->key(['calculation_id' => $calculationId])
      ->fields([
        'code' => ($context['code'] ?? '') !== '' ? mb_substr((string) $context['code'], 0, 64) : NULL,
        'label' => mb_substr((string) ($context['label'] ?? ('Calculatie ' . $calculationId)), 0, 255),
        'package_id' => !empty($context['package_id']) ? (int) $context['package_id'] : NULL,
        'project_id' => !empty($context['project_id']) ? (int) $context['project_id'] : NULL,
        'project_label' => ($context['project_label'] ?? '') !== '' ? mb_substr((string) $context['project_label'], 0, 255) : NULL,
        'updated' => time(),
      ])
      ->execute();
  }

}
