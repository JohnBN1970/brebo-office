<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\ProjectReferenceGatewayInterface;
use Drupal\brebo_finance\Contract\WorkingBudgetImportRepositoryInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * Creates an auditable draft working budget from a locked calculation snapshot.
 */
final class WorkingBudgetImporter {

  private const array ALLOWED_SOURCE_STATUSES = ['established', 'final_budget'];
  private const array COST_COMPONENTS = [
    'labour' => 'arbeid',
    'material' => 'materiaal',
    'equipment' => 'materieel',
    'subcontracting' => 'onderaanneming',
    'other' => 'overig',
  ];

  public function __construct(
    private readonly WorkingBudgetImportRepositoryInterface $repository,
    private readonly ProjectReferenceGatewayInterface $projects,
  ) {}

  /**
   * Imports one calculation snapshot as a draft working budget.
   *
   * The result is deliberately not approved or locked. Technical regrouping,
   * building allocation and multidisciplinary review must happen first.
   */
  public function import(
    int $projectNid,
    int $calculationId,
    string $calculationVersion,
    int $userId,
  ): int {
    if (!$this->projects->exists($projectNid)) {
      throw new UnexpectedValueException('A valid BREBO project is required.');
    }

    $version = $this->repository->calculationVersion($calculationId, $calculationVersion);
    if ($version === NULL || !in_array($version['status'], self::ALLOWED_SOURCE_STATUSES, TRUE)) {
      throw new UnexpectedValueException('Only an established or final-budget calculation may create a working budget.');
    }

    $snapshot = $this->repository->calculationSnapshot($calculationId, $calculationVersion);
    if ($snapshot === NULL) {
      throw new UnexpectedValueException('The locked calculation snapshot is missing.');
    }

    $payload = json_decode($snapshot['payload'], TRUE, 512, JSON_THROW_ON_ERROR);
    $this->validateSnapshot($payload, $snapshot['content_hash'], $calculationId, $calculationVersion);

    if ($this->repository->workingBudgetExists($projectNid, $calculationId, $calculationVersion)) {
      throw new RuntimeException('This calculation snapshot already has a working budget for the project.');
    }

    return $this->repository->transactional(function () use ($projectNid, $calculationId, $calculationVersion, $userId, $snapshot, $payload): int {
      $now = time();
      $budgetId = $this->repository->insertBudget([
          'project_nid' => $projectNid,
          'version' => $this->workingBudgetVersion($calculationId, $calculationVersion),
          'budget_type' => 'working',
          'status' => 'draft',
          'source_calculation_id' => $calculationId,
          'source_calculation_version' => $calculationVersion,
          'currency' => 'EUR',
          'source_content_hash' => $snapshot['content_hash'],
          'content_hash' => NULL,
          'created' => $now,
          'created_by' => $userId,
          'changed' => $now,
          'changed_by' => $userId,
        ]);

      $sortOrder = 0;
      $labourByRate = [];
      foreach ($payload['rows'] as $rowIndex => $row) {
        $quantity = (float) ($row['actual_quantity'] ?? $row['quantity'] ?? 0);
        foreach (self::COST_COMPONENTS as $component => $costCode) {
          $unitCost = (float) ($row['unit_costs'][$component] ?? 0);
          if ($unitCost === 0.0 || $quantity === 0.0) {
            continue;
          }

          if ($component === 'labour') {
            $hours = (float) ($row['budget_hours'] ?? 0);
            $rate = round((float) ($row['labour_rate'] ?? 0), 2);
            if ($hours <= 0 || $rate <= 0) {
              throw new UnexpectedValueException(sprintf(
                'Arbeidsregel %d mist begrote uren of intern uurtarief in de vastgestelde calculatie.',
                $rowIndex + 1,
              ));
            }
            $rateKey = number_format($rate, 2, '.', '');
            $labourByRate[$rateKey] ??= ['hours' => 0.0, 'amount' => 0.0, 'sources' => []];
            $labourByRate[$rateKey]['hours'] += $hours;
            $labourByRate[$rateKey]['amount'] += $hours * $rate;
            $labourByRate[$rateKey]['sources'][] = isset($row['legacy_line_id'])
              ? (string) $row['legacy_line_id']
              : sprintf('snapshot-row-%d', $rowIndex + 1);
            continue;
          }

          $this->repository->insertBudgetLine([
              'budget_id' => $budgetId,
              'line_key' => sprintf('calc-%d-%d-%s', $calculationId, $rowIndex + 1, $component),
              'cost_code' => $costCode,
              'work_package' => (string) ($row['paragraph_id'] ?? ''),
              'description' => (string) ($row['description'] ?? 'Calculatieregel'),
              'quantity' => $this->decimal($quantity),
              'unit' => $row['unit'] ?? NULL,
              'unit_cost_ex_vat' => $this->decimal($unitCost),
              'amount_ex_vat' => $this->decimal($quantity * $unitCost),
              'vat_code' => 'NL_0',
              'vat_rate' => '0.0000',
              'vat_amount' => '0.0000',
              'amount_inc_vat' => $this->decimal($quantity * $unitCost),
              'vat_reverse_charge' => 0,
              'non_deductible_vat_amount' => '0.0000',
              'source_line_ref' => isset($row['legacy_line_id'])
                ? (string) $row['legacy_line_id']
                : sprintf('snapshot-row-%d', $rowIndex + 1),
              'sort_order' => ++$sortOrder,
              'created' => $now,
              'created_by' => $userId,
              'changed' => $now,
              'changed_by' => $userId,
            ]);
        }
      }

      ksort($labourByRate, SORT_NUMERIC);
      foreach ($labourByRate as $rateKey => $labour) {
        $rate = (float) $rateKey;
        $hours = (float) $labour['hours'];
        $amount = $hours * $rate;
        $this->repository->insertBudgetLine([
            'budget_id' => $budgetId,
            'line_key' => sprintf('labour-rate-%s', str_replace('.', '-', $rateKey)),
            'cost_code' => 'arbeid',
            'work_package' => '',
            'description' => sprintf('Arbeid € %.2f/u', $rate),
            'quantity' => $this->decimal($hours),
            'unit' => 'uur',
            'unit_cost_ex_vat' => $this->decimal($rate),
            'amount_ex_vat' => $this->decimal($amount),
            'budget_hours' => $this->decimal($hours),
            'hourly_cost_ex_vat' => $this->decimal($rate),
            'vat_code' => 'NL_0',
            'vat_rate' => '0.0000',
            'vat_amount' => '0.0000',
            'amount_inc_vat' => $this->decimal($amount),
            'vat_reverse_charge' => 0,
            'non_deductible_vat_amount' => '0.0000',
            'source_line_ref' => implode(',', $labour['sources']),
            'sort_order' => ++$sortOrder,
            'created' => $now,
            'created_by' => $userId,
            'changed' => $now,
            'changed_by' => $userId,
          ]);
      }

      if ($sortOrder === 0) {
        throw new UnexpectedValueException('The snapshot contains no transferable direct-cost lines.');
      }

      $this->repository->insertAudit([
          'project_nid' => $projectNid,
          'entity_type' => 'working_budget',
          'entity_id' => $budgetId,
          'action' => 'imported',
          'after_hash' => $snapshot['content_hash'],
          'payload' => json_encode([
            'calculation_id' => $calculationId,
            'calculation_version' => $calculationVersion,
            'source_hash' => $snapshot['content_hash'],
            'imported_lines' => $sortOrder,
          ], JSON_THROW_ON_ERROR),
          'reason' => 'Controlled transfer from approved calculation to draft working budget.',
          'created' => $now,
          'created_by' => $userId,
        ]);

      return $budgetId;
    });
  }

  /**
   * Validates snapshot identity and minimum transferable structure.
   */
  private function validateSnapshot(
    array $payload,
    string $storedHash,
    int $calculationId,
    string $calculationVersion,
  ): void {
    if (($payload['calculation_id'] ?? NULL) !== $calculationId
      || ($payload['version'] ?? NULL) !== $calculationVersion
      || !hash_equals($storedHash, (string) ($payload['content_hash'] ?? ''))
      || !isset($payload['rows'])
      || !is_array($payload['rows'])
    ) {
      throw new UnexpectedValueException('Calculation snapshot identity or structure is invalid.');
    }
  }

  private function workingBudgetVersion(int $calculationId, string $version): string {
    $normalized = preg_replace('/[^A-Za-z0-9._-]/', '-', $version) ?: 'version';
    return substr(sprintf('WB-%d-%s', $calculationId, $normalized), 0, 32);
  }

  private function decimal(float $value): string {
    return number_format($value, 4, '.', '');
  }

}
