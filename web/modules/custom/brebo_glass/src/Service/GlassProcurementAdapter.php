<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_office_core\Service\ProjectPlanningService;
use Drupal\brebo_procurement_control\Service\ProcurementFulfilmentDecisionService;

/** Adapts a glass need to the generic BREBO fulfilment decision engine. */
final class GlassProcurementAdapter {
  public function __construct(
    private readonly GlassAvailabilityService $availability,
    private readonly ProcurementFulfilmentDecisionService $fulfilment,
    private readonly ProjectPlanningService $planning,
  ) {}

  /**
   * @param array<int,array<string,mixed>> $supplierOptions
   * @return array<string,mixed>
   */
  public function decide(int $positionId, float $quantity, string $requiredDate, array $supplierOptions): array {
    // First resolve the glass group and project without reserving anything.
    $baseStock = $this->availability->stockForPosition($positionId, 0.0);
    $reservedQuantity = $this->planning->reservedQuantity(
      (int) $baseStock['project_nid'],
      (string) $baseStock['glass_group_key'],
      $requiredDate,
    );
    $stock = $this->availability->stockForPosition($positionId, $reservedQuantity);

    $decision = $this->fulfilment->decide([
      'quantity' => $quantity,
      'required_date' => $requiredDate,
      'domain' => 'glass',
      'source_reference' => 'glass-position:' . $positionId,
    ], [$stock], $supplierOptions);
    $decision['glass_position_id'] = $positionId;
    $decision['project_nid'] = $stock['project_nid'];
    $decision['glass_group_key'] = $stock['glass_group_key'];
    $decision['planning_reserved_quantity'] = $reservedQuantity;
    return $decision;
  }
}
