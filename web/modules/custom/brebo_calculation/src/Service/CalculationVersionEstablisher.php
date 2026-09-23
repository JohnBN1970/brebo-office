<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Establishes a draft calculation version as one immutable canonical truth.
 */
final class CalculationVersionEstablisher {

  public function __construct(
    private readonly Connection $database,
    private readonly CalculationResultService $resultService,
    private readonly CalculationReadinessInspector $readinessInspector,
    private readonly TimeInterface $time,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @return array<string,mixed>
   */
  public function establish(int $calculationId, string $version, AccountInterface $account): array {
    $versionRow = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAssoc();

    if (!is_array($versionRow)) {
      throw new \RuntimeException('Calculatieversie niet gevonden.');
    }
    if ((string) $versionRow['status'] !== 'draft' || $versionRow['locked_at'] !== NULL) {
      throw new \RuntimeException('Alleen een open conceptversie kan worden vastgesteld.');
    }

    $readiness = $this->readinessInspector->inspect($calculationId, $version);
    if ((int) ($readiness['blocking'] ?? 0) > 0) {
      throw new \RuntimeException('Calculatie kan niet worden vastgesteld zolang readiness blokkades bevat.');
    }

    $result = $this->resultService->calculate($calculationId, $version);
    $structure = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->orderBy('sort_order')
      ->orderBy('depth')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $hashResult = $result;
    unset($hashResult['content_hash'], $hashResult['status'], $hashResult['locked_at'], $hashResult['source']);
    $hashPayload = [
      'calculation_id' => $calculationId,
      'version' => $version,
      'structure' => $structure,
      'canonical_result' => $hashResult,
    ];
    $contentHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

    $snapshotRows = [];
    foreach ((array) ($result['components'] ?? []) as $component) {
      if (!is_array($component) || ($component['kind'] ?? '') !== 'row') {
        continue;
      }
      $lineId = (int) ($component['id'] ?? 0);
      if ($lineId <= 0) {
        continue;
      }
      $domain = $this->database->select('brebo_calculation_row_domain', 'r')
        ->fields('r')
        ->condition('calc_line_id', $lineId)
        ->condition('version', $version)
        ->execute()
        ->fetchAssoc();
      $line = $this->entityTypeManager->getStorage('node')->load($lineId);
      if (!is_array($domain) || !$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line') {
        continue;
      }
      $actualRaw = $line->hasField('field_brebo_actual_quantity') ? $line->get('field_brebo_actual_quantity')->value : NULL;
      $snapshotRows[] = [
        'legacy_line_id' => $lineId,
        'paragraph_id' => (string) ($domain['paragraph_key'] ?? ''),
        'type' => (string) ($domain['rule_type'] ?? 'normal'),
        'description' => (string) ($component['description'] ?? $line->label()),
        'quantity' => (float) ($line->get('field_brebo_contract_quantity')->value ?? 0),
        'actual_quantity' => ($actualRaw === NULL || $actualRaw === '') ? NULL : (float) $actualRaw,
        'unit' => (string) ($component['unit'] ?? ''),
        'budget_hours' => (float) ($line->get('field_brebo_budget_hours')->value ?? 0),
        'labour_rate' => (float) ($line->get('field_brebo_labor_rate')->value ?? 0),
        'unit_costs' => [
          'labour' => (float) ($domain['labour_unit_cost'] ?? 0),
          'material' => (float) ($domain['material_unit_cost'] ?? 0),
          'equipment' => (float) ($domain['equipment_unit_cost'] ?? 0),
          'subcontracting' => (float) ($domain['subcontracting_unit_cost'] ?? 0),
          'other' => (float) ($domain['other_unit_cost'] ?? 0),
        ],
      ];
    }

    $lockedAt = $this->time->getCurrentTime();
    $snapshotResult = $result;
    $snapshotResult['content_hash'] = $contentHash;
    $payload = [
      'schema' => 'canonical_result_v1',
      'calculation_id' => $calculationId,
      'version' => $version,
      'content_hash' => $contentHash,
      'structure' => $structure,
      'rows' => $snapshotRows,
      'canonical_result' => $snapshotResult,
      'readiness' => $readiness,
    ];

    $transaction = $this->database->startTransaction();
    try {
      $existing = (bool) $this->database->select('brebo_calculation_snapshot', 's')
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->countQuery()
        ->execute()
        ->fetchField();
      if ($existing) {
        throw new \RuntimeException('Voor deze calculatieversie bestaat al een immutable snapshot.');
      }

      $this->database->insert('brebo_calculation_snapshot')->fields([
        'calculation_id' => $calculationId,
        'version' => $version,
        'content_hash' => $contentHash,
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        'created' => $lockedAt,
        'created_by' => (int) $account->id(),
      ])->execute();

      $updated = $this->database->update('brebo_calculation_version')
        ->fields([
          'status' => 'established',
          'locked_at' => $lockedAt,
          'locked_by' => (int) $account->id(),
          'content_hash' => $contentHash,
        ])
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->condition('status', 'draft')
        ->isNull('locked_at')
        ->execute();

      if ($updated !== 1) {
        throw new \RuntimeException('Calculatieversie veranderde tijdens het vaststellen.');
      }

      $calculation = $this->entityTypeManager->getStorage('node')->load($calculationId);
      if (!$calculation instanceof NodeInterface || $calculation->bundle() !== 'brebo_calculation') {
        throw new \RuntimeException('Calculatie-node niet gevonden tijdens vaststellen.');
      }
      if ($calculation->hasField('field_brebo_calc_status')) {
        $calculation->set('field_brebo_calc_status', 'Vastgesteld');
        $calculation->save();
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }

    $snapshotResult['status'] = 'established';
    $snapshotResult['locked_at'] = $lockedAt;
    $snapshotResult['source'] = 'immutable_snapshot';
    return $snapshotResult;
  }

}
