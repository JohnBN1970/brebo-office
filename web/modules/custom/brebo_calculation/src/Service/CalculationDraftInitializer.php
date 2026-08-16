<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;

/** Creates the first editable domain version for a newly created calculation. */
final class CalculationDraftInitializer {

  public function __construct(private readonly Connection $database) {}

  public function ensure(NodeInterface $calculation): string {
    if ($calculation->bundle() !== 'brebo_calculation' || $calculation->id() === NULL) {
      throw new \InvalidArgumentException('A saved BREBO calculation is required.');
    }

    $calculationId = (int) $calculation->id();
    $existing = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version'])
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if (is_string($existing) && $existing !== '') {
      return $existing;
    }

    $version = trim($this->stringValue($calculation, 'field_brebo_calc_version', '1.0'));
    $version = $version !== '' ? mb_substr($version, 0, 32) : '1.0';
    $generalCost = $this->floatValue($calculation, 'field_brebo_general_cost_pct');
    $risk = $this->floatValue($calculation, 'field_brebo_risk_pct');
    $profit = $this->floatValue($calculation, 'field_brebo_profit_pct');
    $adjustment = $this->floatValue($calculation, 'field_brebo_com_adjustment');
    $priceDate = trim($this->stringValue($calculation, 'field_brebo_price_date')) ?: NULL;

    $payload = [
      'calculation_id' => $calculationId,
      'version' => $version,
      'status' => 'draft',
      'classification_system' => 'nl_sfb',
      'pricing_mode' => 'closed',
      'commercial_method' => 'tail_costs',
      'general_cost_pct' => $generalCost,
      'risk_pct' => $risk,
      'profit_pct' => $profit,
      'single_margin_pct' => 0.0,
      'commercial_adjustment' => $adjustment,
      'price_date' => $priceDate,
    ];

    $this->database->insert('brebo_calculation_version')->fields([
      'calculation_id' => $calculationId,
      'version' => $version,
      'status' => 'draft',
      'classification_system' => 'nl_sfb',
      'pricing_mode' => 'closed',
      'commercial_method' => 'tail_costs',
      'general_cost_pct' => $generalCost,
      'risk_pct' => $risk,
      'profit_pct' => $profit,
      'single_margin_pct' => 0,
      'commercial_adjustment' => $adjustment,
      'price_date' => $priceDate,
      'price_level' => NULL,
      'locked_at' => NULL,
      'locked_by' => NULL,
      'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)),
    ])->execute();

    return $version;
  }

  private function stringValue(NodeInterface $node, string $fieldName, string $default = ''): string {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return $default;
    }
    return (string) ($node->get($fieldName)->value ?? $default);
  }

  private function floatValue(NodeInterface $node, string $fieldName): float {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return 0.0;
    }
    return max(0.0, (float) ($node->get($fieldName)->value ?? 0));
  }

}
