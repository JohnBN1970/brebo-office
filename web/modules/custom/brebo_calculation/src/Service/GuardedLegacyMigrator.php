<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationPersistenceInterface;
use Drupal\brebo_calculation\Domain\CalculationParameters;
use Drupal\brebo_calculation\Domain\CalculationStatus;
use Drupal\brebo_calculation\Domain\CalculationVersion;
use Drupal\brebo_calculation\Domain\ClassificationSystem;
use Drupal\brebo_calculation\Domain\MigrationWriteResult;
use Drupal\Core\Database\Connection;

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
    private readonly Connection $database,
  ) {}

  public function migrate(int $calculationId, string $version = 'migration-1'): MigrationWriteResult {
    $version = trim($version);
    if ($version === '') {
      throw new \InvalidArgumentException('Migration version is required.');
    }
    if ($this->versionExists($calculationId, $version)) {
      throw new \RuntimeException('Calculation migration blocked: target migration version already exists.');
    }

    $preview = $this->dryRun->preview($calculationId);
    if (!$preview->isSafeToMigrate()) {
      throw new \RuntimeException('Calculation migration blocked: dry-run is not clean.');
    }

    $hash = $this->migrationHash($preview);
    $domainVersion = new CalculationVersion(
      calculationId: $calculationId,
      version: $version,
      status: CalculationStatus::Draft,
      classificationSystem: ClassificationSystem::NlSfb,
      parameters: new CalculationParameters(),
      contentHash: $hash,
    );

    $transaction = $this->database->startTransaction();
    try {
      $this->persistence->saveVersion($calculationId, $domainVersion);
      $this->persistence->replaceStructure($calculationId, $version, $domainVersion->classificationSystem, $preview->structure);

      foreach ($preview->rows as $row) {
        $costs = $row->unitCosts->toArray();
        $this->persistence->saveRowDomain($calculationId, $version, $row->legacyLineId, [
          'paragraph_key' => $row->paragraphId,
          'rule_type' => $row->type->value,
          'location_ref' => $row->locationRef,
          'labour_unit_cost' => $costs['labour'],
          'material_unit_cost' => $costs['material'],
          'equipment_unit_cost' => $costs['equipment'],
          'subcontracting_unit_cost' => $costs['subcontracting'],
          'other_unit_cost' => $costs['other'],
        ]);
      }

      $this->assertWritten($calculationId, $version, count($preview->structure), count($preview->rows), $hash);
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }

    return new MigrationWriteResult(
      calculationId: $calculationId,
      version: $version,
      structureCount: count($preview->structure),
      rowCount: count($preview->rows),
      contentHash: $hash,
    );
  }

  private function versionExists(int $calculationId, string $version): bool {
    return (bool) $this->database->select('brebo_calculation_version', 'v')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->countQuery()->execute()->fetchField();
  }

  private function assertWritten(int $calculationId, string $version, int $structureCount, int $rowCount, string $hash): void {
    $storedStructure = (int) $this->database->select('brebo_calculation_structure', 's')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->countQuery()->execute()->fetchField();
    $storedRows = (int) $this->database->select('brebo_calculation_row_domain', 'r')
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->countQuery()->execute()->fetchField();
    $storedHash = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['content_hash'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()->fetchField();

    if ($storedStructure !== $structureCount || $storedRows !== $rowCount || $storedHash !== $hash) {
      throw new \RuntimeException('Calculation migration verification failed; transaction will be rolled back.');
    }
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
