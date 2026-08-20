<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_procurement_control\Service\ProcurementFulfilmentDecisionService;

/** Adapts a glass need to the generic BREBO fulfilment decision engine. */
final class GlassProcurementAdapter {
  public function __construct(
    private readonly GlassAvailabilityService $availability,
    private readonly ProcurementFulfilmentDecisionService $fulfilment,
  ) {}

  /**
   * @param array<int,array<string,mixed>> $supplierOptions
   * @return array<string,mixed>
   */
  public function decide(int $positionId, float $quantity, string $requiredDate, float $reservedQuantity, array $supplierOptions): array {
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
    return $decision;
  }
}
