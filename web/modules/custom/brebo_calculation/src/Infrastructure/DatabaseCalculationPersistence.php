<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalculationPersistenceInterface;
use Drupal\brebo_calculation\Domain\CalculationSnapshot;
use Drupal\brebo_calculation\Domain\CalculationVersion;
use Drupal\brebo_calculation\Domain\StructureNode;
use Drupal\Core\Database\Connection;

/** Drupal database implementation of calculation persistence. */
final class DatabaseCalculationPersistence implements CalculationPersistenceInterface {

  public function __construct(private readonly Connection $database) {}

  public function saveVersion(int $calculationId, CalculationVersion $version): void {
    $this->database->merge('brebo_calculation_version')
      ->keys(['calculation_id' => $calculationId, 'version' => $version->version])
      ->fields([
        'status' => $version->status->value,
        'classification_system' => $version->classificationSystem->value,
        'pricing_mode' => $version->parameters->pricingMode,
        'commercial_method' => $version->parameters->commercialMethod,
        'general_cost_pct' => $version->parameters->generalCostPct,
        'risk_pct' => $version->parameters->riskPct,
        'profit_pct' => $version->parameters->profitPct,
        'single_margin_pct' => $version->parameters->singleMarginPct,
        'commercial_adjustment' => $version->parameters->commercialAdjustment,
        'price_date' => $version->parameters->priceDate,
        'price_level' => $version->parameters->priceLevel,
        'locked_at' => $version->lockedAt,
        'locked_by' => $version->lockedBy,
        'content_hash' => $version->contentHash,
      ])->execute();
  }

  public function replaceStructure(int $calculationId, string $version, array $nodes): void {
    $transaction = $this->database->startTransaction();
    try {
      $this->database->delete('brebo_calculation_structure')
        ->condition('calculation_id', $calculationId)
        ->condition('version', $version)
        ->execute();
      foreach ($nodes as $node) {
        if (!$node instanceof StructureNode) {
          throw new \InvalidArgumentException('Structure contains an invalid node.');
        }
        $this->database->insert('brebo_calculation_structure')->fields([
          'calculation_id' => $calculationId,
          'version' => $version,
          'node_key' => $node->id,
          'parent_key' => $node->parentId,
          'node_type' => $node->type->value,
          'depth' => $node->depth,
          'classification_system' => '',
          'code' => $node->code,
          'label' => $node->label,
          'sort_order' => $node->sortOrder,
          'location_ref' => $node->locationRef,
        ])->execute();
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  public function saveRowDomain(int $calculationId, string $version, int $calcLineId, array $data): void {
    $allowed = [
      'paragraph_key', 'rule_type', 'location_ref', 'labour_unit_cost',
      'material_unit_cost', 'equipment_unit_cost', 'subcontracting_unit_cost',
      'other_unit_cost', 'distribution_method', 'distribution_payload',
    ];
    $fields = array_intersect_key($data, array_flip($allowed));
    $fields += ['calculation_id' => $calculationId];
    $this->database->merge('brebo_calculation_row_domain')
      ->keys(['calc_line_id' => $calcLineId, 'version' => $version])
      ->fields($fields)
      ->execute();
  }

  public function saveSnapshot(CalculationSnapshot $snapshot, int $created, ?int $createdBy = NULL): void {
    $payload = [
      'calculation_id' => $snapshot->calculationId,
      'version' => $snapshot->version->version,
      'content_hash' => $snapshot->contentHash(),
      'totals' => $snapshot->totals->toArray(),
      'commercial' => $snapshot->commercial->toArray(),
    ];
    $this->database->merge('brebo_calculation_snapshot')
      ->keys(['calculation_id' => $snapshot->calculationId, 'version' => $snapshot->version->version])
      ->fields([
        'content_hash' => $snapshot->contentHash(),
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        'created' => $created,
        'created_by' => $createdBy,
      ])->execute();
  }

}
