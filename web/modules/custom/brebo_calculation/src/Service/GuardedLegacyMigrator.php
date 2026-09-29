<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationPersistenceInterface;
use Drupal\brebo_calculation\Contract\LegacyCalculationSourceInterface;
use Drupal\brebo_calculation\Domain\CalculationParameters;
use Drupal\brebo_calculation\Domain\CalculationStatus;
use Drupal\brebo_calculation\Domain\CalculationVersion;
use Drupal\brebo_calculation\Domain\ClassificationSystem;
use Drupal\brebo_calculation\Domain\MigrationWriteResult;
use Drupal\brebo_calculation\Contract\LegacyMigrationRepositoryInterface;

/**
 * Writes a legacy calculation only after a fresh clean dry-run.
 *
 * The migration is deliberately limited to an editable draft domain version.
 * Locking/snapshotting happens later through the normal establishment workflow.
 */
final class GuardedLegacyMigrator {

  public function __construct(
    private readonly LegacyDryRunService $dryRun,
    private readonly CalculationPersistenceInterface $persistence,
    private readonly LegacyCalculationSourceInterface $legacySource,
    private readonly LegacyMigrationRepositoryInterface $repository,
  ) {}

  public function migrate(int $calculationId, string $version = 'migration-1'): MigrationWriteResult {
    $version = trim($version);
    if ($version === '') {
      throw new \InvalidArgumentException('Migration version is required.');
    }
    if ($this->repository->versionExists($calculationId, $version)) {
      throw new \RuntimeException('Calculation migration blocked: target migration version already exists.');
    }

    $preview = $this->dryRun->preview($calculationId);
    if (!$preview->isSafeToMigrate()) {
      throw new \RuntimeException('Calculation migration blocked: dry-run is not clean.');
    }

    $hash = $this->migrationHash($preview);
    $legacy = $this->legacySource->load($calculationId);
    $legacyLines = [];
    foreach ($legacy['lines'] as $legacyLine) {
      $legacyLines[(int) $legacyLine['id']] = $legacyLine;
    }
    $domainVersion = new CalculationVersion(
      calculationId: $calculationId,
      version: $version,
      status: CalculationStatus::Draft,
      classificationSystem: ClassificationSystem::NlSfb,
      parameters: new CalculationParameters(),
      contentHash: $hash,
    );

    $this->repository->transactional(function () use ($calculationId, $version, $domainVersion, $preview, $legacyLines, $hash): void {
      $this->persistence->saveVersion($calculationId, $domainVersion);
      $this->persistence->replaceStructure($calculationId, $version, $domainVersion->classificationSystem, $preview->structure);

      foreach ($preview->rows as $row) {
        $costs = $row->unitCosts->toArray();
        $legacyLine = $legacyLines[$row->legacyLineId] ?? NULL;
        $this->persistence->saveRowDomain($calculationId, $version, $row->legacyLineId, [
          'paragraph_key' => $row->paragraphId,
          'rule_type' => $row->type->value,
          'location_ref' => $row->locationRef,
          'description' => $row->description,
          'contract_quantity' => $row->quantity,
          'actual_quantity' => $row->actualQuantity,
          'unit' => $row->unit,
          'budget_hours' => is_array($legacyLine) ? (float) ($legacyLine['budget_hours'] ?? 0) : 0.0,
          'labour_rate' => is_array($legacyLine) ? (float) ($legacyLine['labour_rate'] ?? 0) : 0.0,
          'labour_unit_cost' => $costs['labour'],
          'material_unit_cost' => $costs['material'],
          'equipment_unit_cost' => $costs['equipment'],
          'subcontracting_unit_cost' => $costs['subcontracting'],
          'other_unit_cost' => $costs['other'],
        ]);
      }

      $this->repository->assertWritten($calculationId, $version, count($preview->structure), count($preview->rows), $hash);    });

    return new MigrationWriteResult(
      calculationId: $calculationId,
      version: $version,
      structureCount: count($preview->structure),
      rowCount: count($preview->rows),
      contentHash: $hash,
    );
  }

  private function migrationHash(object $preview): string {
    $payload = [
      'calculation_id' => $preview->calculationId,
      'structure' => array_map(static fn ($node): array => [
        'id' => $node->id,
        'parent_id' => $node->parentId,
        'type' => $node->type->value,
        'code' => $node->code,
        'label' => $node->label,
        'depth' => $node->depth,
        'sort_order' => $node->sortOrder,
        'location_ref' => $node->locationRef,
      ], $preview->structure),
      'rows' => array_map(static fn ($row): array => [
        'legacy_line_id' => $row->legacyLineId,
        'paragraph_id' => $row->paragraphId,
        'type' => $row->type->value,
        'quantity' => $row->quantity,
        'actual_quantity' => $row->actualQuantity,
        'unit_costs' => $row->unitCosts->toArray(),
        'location_ref' => $row->locationRef,
      ], $preview->rows),
      'totals' => $preview->totals->toArray(),
    ];
    return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
  }

}
