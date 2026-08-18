<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Produces immutable, deterministic project stress scenarios.
 */
final class FinancialScenarioEngine {

  public function __construct(
    private readonly Connection $database,
    private readonly VatCalculator $decimal,
  ) {}

  /**
   * Creates a scenario definition with explicit, non-overlapping assumptions.
   *
   * @param array<string, mixed> $data
   */
  public function createScenario(array $data, int $actorUid): int {
    foreach (['project_nid', 'scenario_name', 'scenario_type', 'remaining_cost_multiplier', 'receipt_delay_days', 'assumption_evidence'] as $required) {
      if (!isset($data[$required]) || $data[$required] === '') {
        throw new InvalidArgumentException("$required is required.");
      }
    }
    if (!in_array($data['scenario_type'], ['optimistic', 'expected', 'stress', 'custom'], TRUE)) {
      throw new InvalidArgumentException('Unsupported scenario type.');
    }
    $multiplier = (string) $data['remaining_cost_multiplier'];
    if ($this->decimal->compare($multiplier, '0.5000') < 0 || $this->decimal->compare($multiplier, '3.0000') > 0) {
      throw new InvalidArgumentException('Remaining-cost multiplier must be between 0.5000 and 3.0000.');
    }
    $receiptDelay = (int) $data['receipt_delay_days'];
    if ($receiptDelay < 0 || $receiptDelay > 365) {
      throw new InvalidArgumentException('Receipt delay must be between 0 and 365 days.');
    }
    $moneyFields = [
      'labour_productivity_impact_ex_vat',
      'purchase_price_impact_ex_vat',
      'delay_cost_impact_ex_vat',
      'credit_loss_impact_ex_vat',
      'revenue_change_ex_vat',
      'additional_risk_reserve_ex_vat',
      'delayed_receipts_inc_vat',
    ];
    $values = [];
    foreach ($moneyFields as $field) {
      $values[$field] = (string) ($data[$field] ?? '0');
      if ($field !== 'revenue_change_ex_vat' && $this->decimal->compare($values[$field], '0') < 0) {
        throw new InvalidArgumentException("$field cannot be negative.");
      }
    }
    $assumptions = [
      'remaining_cost_multiplier' => $multiplier,
      'receipt_delay_days' => $receiptDelay,
      ...$values,
      'notes' => (string) ($data['notes'] ?? ''),
      'evidence' => $data['assumption_evidence'],
    ];
    $now = time();
    return (int) $this->database->insert('brebo_finance_scenario')
      ->fields([
        'project_nid' => (int) $data['project_nid'],
        'scenario_name' => (string) $data['scenario_name'],
        'scenario_type' => (string) $data['scenario_type'],
        'status' => 'draft',
        'remaining_cost_multiplier' => $multiplier,
        'labour_productivity_impact_ex_vat' => $values['labour_productivity_impact_ex_vat'],
        'purchase_price_impact_ex_vat' => $values['purchase_price_impact_ex_vat'],
        'delay_cost_impact_ex_vat' => $values['delay_cost_impact_ex_vat'],
        'credit_loss_impact_ex_vat' => $values['credit_loss_impact_ex_vat'],
        'revenue_change_ex_vat' => $values['revenue_change_ex_vat'],
        'additional_risk_reserve_ex_vat' => $values['additional_risk_reserve_ex_vat'],
        'receipt_delay_days' => $receiptDelay,
        'delayed_receipts_inc_vat' => $values['delayed_receipts_inc_vat'],
        'assumption_payload' => json_encode($assumptions, JSON_THROW_ON_ERROR),
        'assumption_hash' => hash('sha256', json_encode($assumptions, JSON_THROW_ON_ERROR)),
        'created' => $now,
        'created_by' => $actorUid,
        'changed' => $now,
        'changed_by' => $actorUid,
      ])->execute();
  }

  /**
   * Freezes assumptions and calculates an immutable result from one forecast.
   *
   * Activation requires a different user from the scenario creator.
   *
   * @return array<string, mixed>
   */
  public function activateAndCalculate(int $scenarioId, int $forecastSnapshotId, int $approverUid): array {
    $scenario = $this->database->select('brebo_finance_scenario', 's')
      ->fields('s')
      ->condition('id', $scenarioId)
      ->execute()->fetchAssoc();
    if ($scenario === FALSE || $scenario['status'] !== 'draft') {
      throw new RuntimeException('Only a draft scenario can be activated.');
    }
    if ((int) $scenario['created_by'] === $approverUid) {
      throw new RuntimeException('A second person must approve the scenario assumptions.');
    }
    $forecast = $this->database->select('brebo_finance_forecast_snapshot', 'f')
      ->fields('f')
      ->condition('id', $forecastSnapshotId)
      ->execute()->fetchAssoc();
    if ($forecast === FALSE || (int) $forecast['project_nid'] !== (int) $scenario['project_nid']) {
      throw new RuntimeException('Scenario and forecast must belong to the same project.');
    }

    $multiplierImpact = $this->decimal->subtract(
      $this->decimal->multiply((string) $forecast['forecast_remaining_cost_ex_vat'], (string) $scenario['remaining_cost_multiplier']),
      (string) $forecast['forecast_remaining_cost_ex_vat'],
    );
    $explicitCostImpact = '0.0000';
    foreach (['labour_productivity_impact_ex_vat', 'purchase_price_impact_ex_vat', 'delay_cost_impact_ex_vat'] as $field) {
      $explicitCostImpact = $this->decimal->add($explicitCostImpact, (string) $scenario[$field]);
    }
    $totalCostImpact = $this->decimal->add($multiplierImpact, $explicitCostImpact);
    $adjustedEndCost = $this->decimal->add((string) $forecast['forecast_end_cost_ex_vat'], $totalCostImpact);
    $adjustedRevenue = $this->decimal->add((string) $forecast['current_revenue_ex_vat'], (string) $scenario['revenue_change_ex_vat']);
    $adjustedRevenue = $this->decimal->subtract($adjustedRevenue, (string) $scenario['credit_loss_impact_ex_vat']);
    $adjustedReserve = $this->decimal->add((string) $forecast['risk_reserve_ex_vat'], (string) $scenario['additional_risk_reserve_ex_vat']);
    $adjustedResult = $this->decimal->subtract($adjustedRevenue, $adjustedEndCost);
    $adjustedResult = $this->decimal->subtract($adjustedResult, $adjustedReserve);
    $margin = $this->decimal->percentage($adjustedResult, $adjustedRevenue);

    $payload = [
      'scenario_id' => $scenarioId,
      'forecast_snapshot_id' => $forecastSnapshotId,
      'forecast_content_hash' => $forecast['content_hash'],
      'assumption_hash' => $scenario['assumption_hash'],
      'baseline_revenue_ex_vat' => $forecast['current_revenue_ex_vat'],
      'baseline_end_cost_ex_vat' => $forecast['forecast_end_cost_ex_vat'],
      'remaining_cost_multiplier_impact_ex_vat' => $multiplierImpact,
      'explicit_cost_impact_ex_vat' => $explicitCostImpact,
      'adjusted_revenue_ex_vat' => $adjustedRevenue,
      'adjusted_end_cost_ex_vat' => $adjustedEndCost,
      'adjusted_risk_reserve_ex_vat' => $adjustedReserve,
      'adjusted_result_ex_vat' => $adjustedResult,
      'adjusted_margin_pct' => $margin,
      'receipt_delay_days' => (int) $scenario['receipt_delay_days'],
      'delayed_receipts_inc_vat' => $scenario['delayed_receipts_inc_vat'],
      'cash_note' => 'Receipt timing impact is explicit and is not treated as a cost or probability-weighted.',
    ];
    $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    $now = time();
    $snapshotId = (int) $this->database->insert('brebo_finance_scenario_snapshot')
      ->fields([
        'project_nid' => (int) $scenario['project_nid'],
        'scenario_id' => $scenarioId,
        'forecast_snapshot_id' => $forecastSnapshotId,
        'snapshot_date' => date('Y-m-d', $now),
        'adjusted_revenue_ex_vat' => $adjustedRevenue,
        'adjusted_end_cost_ex_vat' => $adjustedEndCost,
        'adjusted_risk_reserve_ex_vat' => $adjustedReserve,
        'adjusted_result_ex_vat' => $adjustedResult,
        'adjusted_margin_pct' => $margin,
        'receipt_delay_days' => (int) $scenario['receipt_delay_days'],
        'delayed_receipts_inc_vat' => $scenario['delayed_receipts_inc_vat'],
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'content_hash' => $hash,
        'created' => $now,
        'created_by' => $approverUid,
      ])->execute();
    $this->database->update('brebo_finance_scenario')
      ->fields([
        'status' => 'active',
        'approved' => $now,
        'approved_by' => $approverUid,
        'changed' => $now,
        'changed_by' => $approverUid,
      ])
      ->condition('id', $scenarioId)
      ->execute();

    return ['id' => $snapshotId, 'content_hash' => $hash] + $payload;
  }

}
