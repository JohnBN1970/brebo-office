<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_calculation\Service\CalculationNormLibrary;

/** Translates an approved glass position into calculation-ready object data. */
final class GlassCalculationContextBuilder {
  public function __construct(
    private readonly GlassPositionRepository $positions,
    private readonly CalculationNormLibrary $norms,
  ) {}

  /** @return array<string,mixed> */
  public function build(int $positionId): array {
    $position = $this->positions->find($positionId);
    if (!$position) {
      throw new \InvalidArgumentException('Glaspositie bestaat niet.');
    }
    if (($position['technical_status'] ?? '') !== 'approved') {
      throw new \RuntimeException('Alleen technisch vrijgegeven glasposities mogen naar de calculatie.');
    }

    $quantity = max(1, (int) ($position['quantity'] ?? 1));
    $areaEach = max(0.0, (float) ($position['area_m2'] ?? 0));
    $areaTotal = $areaEach * $quantity;
    $context = [
      'application_type' => (string) ($position['application_type'] ?? ''),
      'glass_type' => (string) ($position['glass_type'] ?? ''),
      'composition' => (string) ($position['composition'] ?? ''),
      'width_mm' => (float) ($position['width_mm'] ?? 0),
      'height_mm' => (float) ($position['height_mm'] ?? 0),
      'area_m2' => $areaEach,
      'quantity' => $quantity,
      'wind_utilization' => (float) ($position['wind_utilization'] ?? 0),
    ];

    $labourHoursPerM2 = $this->norms->value('glass', 'installation_hours_per_m2', $context, 0.75);
    $wasteFactor = $this->norms->value('glass', 'material_waste_factor', $context, 1.05);
    $handlingHoursPerPiece = $this->norms->value('glass', 'handling_hours_per_piece', $context, 0.10);

    return [
      'source_type' => 'glass_position',
      'source_id' => $positionId,
      'building_nid' => (int) ($position['building_nid'] ?? 0),
      'project_nid' => (int) ($position['project_nid'] ?? 0),
      'position_code' => (string) ($position['position_code'] ?? ''),
      'description' => trim('Glas ' . (string) ($position['position_code'] ?? '') . ' ' . (string) ($position['composition'] ?? '')),
      'quantity' => $quantity,
      'area_each_m2' => $areaEach,
      'area_total_m2' => $areaTotal,
      'material_quantity_m2' => $areaTotal * $wasteFactor,
      'labour_hours' => ($areaTotal * $labourHoursPerM2) + ($quantity * $handlingHoursPerPiece),
      'norms' => [
        'installation_hours_per_m2' => $labourHoursPerM2,
        'material_waste_factor' => $wasteFactor,
        'handling_hours_per_piece' => $handlingHoursPerPiece,
      ],
      'context' => $context,
      'approval_checksum' => (string) ($position['approval_checksum'] ?? ''),
    ];
  }
}
