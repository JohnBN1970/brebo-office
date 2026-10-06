<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationClockInterface;
use Drupal\brebo_calculation\Contract\CalculationEstablishmentRepositoryInterface;
use Drupal\brebo_calculation\Contract\CalculationLegacyLineMirrorMapInterface;
use Drupal\brebo_calculation\Contract\CalculationLineReadModelInterface;

/**
 * Establishes a draft calculation version as one immutable canonical truth.
 */
final class CalculationVersionEstablisher {

  public function __construct(
    private readonly CalculationEstablishmentRepositoryInterface $repository,
    private readonly CalculationResultService $resultService,
    private readonly CalculationReadinessInspector $readinessInspector,
    private readonly CalculationClockInterface $clock,
    private readonly CalculationLineReadModelInterface $lineReadModel,
    private readonly CalculationLegacyLineMirrorMapInterface $legacyMirrorMap,
  ) {}

  /**
   * @return array<string,mixed>
   */
  public function establish(int $calculationId, string $version, int $actorId): array {
    $versionRow = $this->repository->version($calculationId, $version);

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
    $structure = $this->repository->structure($calculationId, $version);

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
    $snapshotRowIds = [];
    foreach ((array) ($result['components'] ?? []) as $component) {
      if (is_array($component) && ($component['kind'] ?? '') === 'row' && (int) ($component['id'] ?? 0) > 0) {
        $snapshotRowIds[] = (int) $component['id'];
      }
    }
    $rowData = $this->lineReadModel->loadMany($snapshotRowIds, $version);
    foreach ((array) ($result['components'] ?? []) as $component) {
      if (!is_array($component) || ($component['kind'] ?? '') !== 'row') {
        continue;
      }
      $rowId = (int) ($component['id'] ?? 0);
      if ($rowId <= 0) {
        continue;
      }
      $domain = $this->repository->rowDomain($rowId, $version);
      $line = $rowData[$rowId] ?? NULL;
      if (!is_array($domain) || !is_array($line)) {
        continue;
      }
      $snapshotRows[] = [
        'row_id' => $rowId,
        'legacy_line_id' => $this->legacyMirrorMap->legacyLineId($calculationId, $version, $rowId),
        'paragraph_id' => (string) ($domain['paragraph_key'] ?? ''),
        'type' => (string) ($domain['rule_type'] ?? 'normal'),
        'description' => (string) ($component['description'] ?? $line['description']),
        'quantity' => (float) ($line['contract_quantity'] ?? 0),
        'actual_quantity' => $line['actual_quantity'] ?? NULL,
        'unit' => (string) ($component['unit'] ?? ''),
        'budget_hours' => (float) ($line['budget_hours'] ?? 0),
        'labour_rate' => (float) ($line['labour_rate'] ?? 0),
        'unit_costs' => [
          'labour' => (float) ($domain['labour_unit_cost'] ?? 0),
          'material' => (float) ($domain['material_unit_cost'] ?? 0),
          'equipment' => (float) ($domain['equipment_unit_cost'] ?? 0),
          'subcontracting' => (float) ($domain['subcontracting_unit_cost'] ?? 0),
          'other' => (float) ($domain['other_unit_cost'] ?? 0),
        ],
      ];
    }

    $lockedAt = $this->clock->now();
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

    $this->repository->persistSnapshotAndLock(
      $calculationId,
      $version,
      [
        'calculation_id' => $calculationId,
        'version' => $version,
        'content_hash' => $contentHash,
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        'created' => $lockedAt,
        'created_by' => $actorId,
      ],
      [
        'status' => 'established',
        'locked_at' => $lockedAt,
        'locked_by' => $actorId,
        'content_hash' => $contentHash,
      ],
    );

    $snapshotResult['status'] = 'established';
    $snapshotResult['locked_at'] = $lockedAt;
    $snapshotResult['source'] = 'immutable_snapshot';
    return $snapshotResult;
  }

}
