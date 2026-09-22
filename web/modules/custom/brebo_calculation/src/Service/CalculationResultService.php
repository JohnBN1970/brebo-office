<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Domain\CalculationParameters;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Builds the canonical commercial result for one calculation version.
 */
final class CalculationResultService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CommercialCalculator $commercialCalculator,
  ) {}

  /**
   * @return array<string,mixed>
   */
  public function calculate(int $calculationId, ?string $versionName = NULL): array {
    $query = $this->database->select('brebo_calculation_version', 'v')->fields('v')
      ->condition('calculation_id', $calculationId);
    if ($versionName !== NULL) {
      $query->condition('version', $versionName);
    }
    else {
      $query->orderBy('id', 'DESC')->range(0, 1);
    }
    $version = $query->execute()->fetchAssoc();
    if (!is_array($version)) {
      throw new \RuntimeException('Calculatiedomeinversie niet gevonden.');
    }

    if ((string) $version['status'] !== 'draft' || $version['locked_at'] !== NULL) {
      $snapshot = $this->database->select('brebo_calculation_snapshot', 's')
        ->fields('s', ['payload'])
        ->condition('calculation_id', $calculationId)
        ->condition('version', (string) $version['version'])
        ->execute()
        ->fetchField();
      if (is_string($snapshot) && $snapshot !== '') {
        $payload = json_decode($snapshot, TRUE, 512, JSON_THROW_ON_ERROR);
        if (is_array($payload)) {
          return $this->resultFromSnapshot($version, $payload);
        }
      }
      throw new \RuntimeException('Een vergrendelde calculatieversie kan alleen uit de onveranderlijke snapshot worden berekend.');
    }

    $parameters = new CalculationParameters(
      pricingMode: (string) $version['pricing_mode'],
      commercialMethod: (string) $version['commercial_method'],
      generalCostPct: (float) $version['general_cost_pct'],
      riskPct: (float) $version['risk_pct'],
      profitPct: (float) $version['profit_pct'],
      singleMarginPct: (float) $version['single_margin_pct'],
      commercialAdjustment: (float) $version['commercial_adjustment'],
      priceDate: $version['price_date'] ?: NULL,
      priceLevel: $version['price_level'] ?: NULL,
    );

    $rows = $this->database->select('brebo_calculation_row_domain', 'r')->fields('r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', (string) $version['version'])
      ->orderBy('calc_line_id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $lineIds = array_map(static fn (array $row): int => (int) $row['calc_line_id'], $rows);
    $entities = $lineIds ? $this->entityTypeManager->getStorage('node')->loadMultiple($lineIds) : [];

    $pricedDirect = 0.0;
    $optionsDirect = 0.0;
    $components = [];
    foreach ($rows as $row) {
      $lineId = (int) $row['calc_line_id'];
      $line = $entities[$lineId] ?? NULL;
      if (!$line instanceof NodeInterface) {
        continue;
      }
      $ruleType = (string) ($row['rule_type'] ?? 'normal');
      if ($ruleType === 'note') {
        continue;
      }
      $quantity = $line->hasField('field_brebo_contract_quantity') ? (float) ($line->get('field_brebo_contract_quantity')->value ?? 0) : 0.0;
      $unitDirect = (float) $row['labour_unit_cost'] + (float) $row['material_unit_cost'] + (float) $row['equipment_unit_cost'] + (float) $row['subcontracting_unit_cost'] + (float) $row['other_unit_cost'];
      $direct = $quantity * $unitDirect;
      if ($ruleType === 'option') {
        $optionsDirect += $direct;
      }
      else {
        $pricedDirect += $direct;
      }
      $components['line_' . $lineId] = [
        'kind' => 'row', 'id' => $lineId, 'rule_type' => $ruleType,
        'description' => (string) ($line->get('field_brebo_line_description')->value ?? $line->label()),
        'quantity' => $quantity,
        'unit' => (string) ($line->get('field_brebo_unit')->value ?? ''),
        'direct_cost' => $direct,
      ];
    }

    $instances = $this->database->select('brebo_calculation_recipe_instance', 'i')->fields('i')
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', (string) $version['version'])
      ->orderBy('sort_order')->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if ($instances) {
      $ids = array_map(static fn (array $instance): int => (int) $instance['id'], $instances);
      $recipeRows = $this->database->select('brebo_calculation_recipe_instance_line', 'l')->fields('l')
        ->condition('recipe_instance_id', $ids, 'IN')
        ->orderBy('recipe_instance_id')->orderBy('sort_order')->execute()->fetchAll(\PDO::FETCH_ASSOC);
      $byInstance = [];
      foreach ($recipeRows as $recipeRow) {
        $byInstance[(int) $recipeRow['recipe_instance_id']][] = $recipeRow;
      }
      foreach ($instances as $instance) {
        $instanceId = (int) $instance['id'];
        $direct = 0.0;
        foreach ($byInstance[$instanceId] ?? [] as $recipeRow) {
          $quantity = $recipeRow['manual_quantity'] !== NULL && $recipeRow['manual_quantity'] !== '' ? (float) $recipeRow['manual_quantity'] : (float) ($recipeRow['calculated_quantity'] ?? 0);
          $quantity *= 1 + ((float) ($recipeRow['waste_pct'] ?? 0) / 100);
          $direct += $quantity * (float) ($recipeRow['unit_cost'] ?? 0);
        }
        $pricedDirect += $direct;
        $components['recipe_' . $instanceId] = [
          'kind' => 'recipe', 'id' => $instanceId, 'rule_type' => 'normal',
          'description' => (string) $instance['name'],
          'quantity' => (float) $instance['quantity'],
          'unit' => (string) ($instance['unit'] ?? ''),
          'direct_cost' => $direct,
        ];
      }
    }

    $commercial = $this->commercialCalculator->calculate($pricedDirect, $parameters);
    return [
      'calculation_id' => $calculationId,
      'version' => (string) $version['version'],
      'content_hash' => (string) ($version['content_hash'] ?? ''),
      'status' => (string) $version['status'],
      'locked_at' => $version['locked_at'] !== NULL ? (int) $version['locked_at'] : NULL,
      'parameters' => [
        'pricing_mode' => $parameters->pricingMode, 'commercial_method' => $parameters->commercialMethod,
        'general_cost_pct' => $parameters->generalCostPct, 'risk_pct' => $parameters->riskPct,
        'profit_pct' => $parameters->profitPct, 'single_margin_pct' => $parameters->singleMarginPct,
        'commercial_adjustment' => $parameters->commercialAdjustment,
        'price_date' => $parameters->priceDate, 'price_level' => $parameters->priceLevel,
      ],
      'priced_direct_cost' => $pricedDirect,
      'options_direct_cost' => $optionsDirect,
      'commercial_result' => $commercial->toArray(),
      'components' => $components,
    ];
  }

  /**
   * @param array<string,mixed> $version
   * @param array<string,mixed> $payload
   * @return array<string,mixed>
   */
  private function resultFromSnapshot(array $version, array $payload): array {
    $commercial = is_array($payload['commercial'] ?? NULL) ? $payload['commercial'] : [];
    $totals = is_array($payload['totals'] ?? NULL) ? $payload['totals'] : [];
    $pricedDirect = (float) ($commercial['direct_cost'] ?? $commercial['directCost'] ?? $totals['priced_scope'] ?? $totals['pricedScope'] ?? 0);
    $optionsDirect = (float) ($totals['options'] ?? 0);
    return [
      'calculation_id' => (int) $version['calculation_id'],
      'version' => (string) $version['version'],
      'content_hash' => (string) ($version['content_hash'] ?? ''),
      'status' => (string) $version['status'],
      'locked_at' => $version['locked_at'] !== NULL ? (int) $version['locked_at'] : NULL,
      'parameters' => [
        'pricing_mode' => (string) $version['pricing_mode'],
        'commercial_method' => (string) $version['commercial_method'],
        'general_cost_pct' => (float) $version['general_cost_pct'],
        'risk_pct' => (float) $version['risk_pct'],
        'profit_pct' => (float) $version['profit_pct'],
        'single_margin_pct' => (float) $version['single_margin_pct'],
        'commercial_adjustment' => (float) $version['commercial_adjustment'],
        'price_date' => $version['price_date'] ?: NULL,
        'price_level' => $version['price_level'] ?: NULL,
      ],
      'priced_direct_cost' => $pricedDirect,
      'options_direct_cost' => $optionsDirect,
      'commercial_result' => $commercial,
      'components' => [],
      'source' => 'immutable_snapshot',
    ];
  }

}
