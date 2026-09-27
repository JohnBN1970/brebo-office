<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationAccessGatewayInterface;
use Drupal\Core\Database\Connection;

/** Updates draft calculation parameters as a guarded domain command. */
final class CalculationParametersManager {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationAccessGatewayInterface $accessGateway,
  ) {}

  /** @param array<string,mixed> $input */
  public function update(int $calculationId, string $version, array $input, int $actorId): void {
    $this->accessGateway->assertCanEditWorkbench($calculationId, $actorId);

    $current = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAssoc();
    if (!$current || $current['locked_at'] !== NULL || (string) $current['status'] !== 'draft') {
      throw new \RuntimeException('Deze calculatieversie is inmiddels vergrendeld of gewijzigd.');
    }

    $values = [
      'pricing_mode' => $this->enum($input, 'pricing_mode', ['cost_price', 'sales_price'], (string) $current['pricing_mode']),
      'commercial_method' => $this->enum($input, 'commercial_method', ['tail_costs', 'single_margin'], (string) $current['commercial_method']),
      'general_cost_pct' => $this->nonNegativeFloat($input, 'general_cost_pct', (float) $current['general_cost_pct']),
      'risk_pct' => $this->nonNegativeFloat($input, 'risk_pct', (float) $current['risk_pct']),
      'profit_pct' => $this->nonNegativeFloat($input, 'profit_pct', (float) $current['profit_pct']),
      'single_margin_pct' => $this->nonNegativeFloat($input, 'single_margin_pct', (float) $current['single_margin_pct']),
      'commercial_adjustment' => isset($input['commercial_adjustment']) && is_numeric($input['commercial_adjustment'])
        ? (float) $input['commercial_adjustment']
        : (float) $current['commercial_adjustment'],
      'price_date' => array_key_exists('price_date', $input)
        ? (($date = trim((string) $input['price_date'])) !== '' ? $date : NULL)
        : ($current['price_date'] ?: NULL),
      'price_level' => array_key_exists('price_level', $input)
        ? (($level = trim((string) $input['price_level'])) !== '' ? mb_substr($level, 0, 64) : NULL)
        : ($current['price_level'] ?: NULL),
    ];

    $hashPayload = $current;
    unset($hashPayload['id'], $hashPayload['content_hash']);
    foreach ($values as $key => $value) {
      $hashPayload[$key] = $value;
    }
    ksort($hashPayload);
    $values['content_hash'] = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    $updated = $this->database->update('brebo_calculation_version')
      ->fields($values)
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('status', 'draft')
      ->isNull('locked_at')
      ->condition('content_hash', (string) ($current['content_hash'] ?? ''))
      ->execute();
    if ($updated !== 1) {
      throw new \RuntimeException('Parameters konden niet veilig worden opgeslagen omdat de calculatie ondertussen wijzigde.');
    }
  }

  /** @param array<string,mixed> $input @param list<string> $allowed */
  private function enum(array $input, string $key, array $allowed, string $default): string {
    $value = array_key_exists($key, $input) ? (string) $input[$key] : $default;
    if (!in_array($value, $allowed, TRUE)) {
      throw new \InvalidArgumentException($key . ' has an invalid value.');
    }
    return $value;
  }

  /** @param array<string,mixed> $input */
  private function nonNegativeFloat(array $input, string $key, float $default): float {
    $value = array_key_exists($key, $input) ? $input[$key] : $default;
    if (!is_numeric($value) || (float) $value < 0) {
      throw new \InvalidArgumentException($key . ' must be a non-negative number.');
    }
    return (float) $value;
  }

}
