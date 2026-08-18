<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

use Drupal\brebo_calculation\Service\CalculationNormLibrary;

/** Converts an approved glass position into calculation-ready quantities. */
final class GlassCalculationLineFactory {

  public function __construct(private readonly CalculationNormLibrary $norms) {}

  /** @param array<string, mixed> $position @return array<int, array<string, mixed>> */
  public function build(array $position): array {
    if ((string) ($position['technical_status'] ?? '') !== 'approved') throw new \InvalidArgumentException('Alleen technisch vrijgegeven glasposities mogen naar de calculatie.');
    $positionId = (int) ($position['id'] ?? 0); $code = trim((string) ($position['position_code'] ?? '')); $quantity = (int) ($position['quantity'] ?? 0);
    $areaPerPane = (float) ($position['area_m2'] ?? 0.0); $perimeterPerPane = (float) ($position['perimeter_m'] ?? 0.0); $weightPerPane = (float) ($position['estimated_weight_kg'] ?? 0.0);
    $composition = trim((string) ($position['composition'] ?? ''));
    if ($positionId <= 0 || $code === '' || $quantity <= 0 || $areaPerPane <= 0) throw new \InvalidArgumentException('Vrijgegeven glaspositie mist calculatiegegevens.');

    $context = [
      'glass_type' => strtolower((string) ($position['glass_type'] ?? '')),
      'application_type' => (string) ($position['application_type'] ?? 'standard'),
      'area_m2' => $areaPerPane,
      'weight_kg' => $weightPerPane,
      'quantity' => $quantity,
    ];
    $netArea = round($areaPerPane * $quantity, 4);
    $wastePct = $this->norms->value('glass', 'waste_pct', $context, $this->fallbackWaste($context));
    $purchaseArea = round($netArea * (1 + ($wastePct / 100)), 4);
    $totalPerimeter = round(max(0.0, $perimeterPerPane) * $quantity, 3);
    $sealantFactor = $this->norms->value('glass', 'sealant_factor', $context, 1.05);
    $blocksPerPane = $this->norms->value('glass', 'setting_block_sets_per_pane', $context, 2.0);
    $baseHours = $this->norms->value('glass', 'installation_base_hours', $context, 0.35);
    $hoursPerM2 = $this->norms->value('glass', 'installation_hours_per_m2', $context, 0.30);
    $weightSurcharge = $this->norms->value('glass', 'installation_weight_surcharge_hours', $context, $weightPerPane > 100 ? 0.75 : ($weightPerPane > 50 ? 0.25 : 0.0));
    $applicationSurcharge = $this->norms->value('glass', 'installation_application_surcharge_hours', $context, in_array($context['application_type'], ['overhead', 'fall_protection', 'fire_separation'], TRUE) ? 0.35 : 0.0);
    $installationHours = round(($baseHours + ($areaPerPane * $hoursPerM2) + $weightSurcharge + $applicationSurcharge) * $quantity, 3);
    $totalWeight = round(max(0.0, $weightPerPane) * $quantity, 2);
    $label = $composition !== '' ? sprintf('%s - %s', $code, $composition) : $code;

    $lines = [
      ['key' => 'glass', 'description' => sprintf('Glas %s (netto %.3f m2 + %.1f%% verlies)', $label, $netArea, $wastePct), 'quantity' => $purchaseArea, 'unit' => 'm2', 'cost_category' => 'Materiaal', 'source_reference' => (string) $positionId],
      ['key' => 'installation', 'description' => sprintf('Montage glaspositie %s', $code), 'quantity' => $installationHours, 'unit' => 'uur', 'cost_category' => 'Arbeid', 'source_reference' => (string) $positionId],
    ];
    if ($totalPerimeter > 0) $lines[] = ['key' => 'sealant', 'description' => sprintf('Beglazingskit / afdichting glaspositie %s', $code), 'quantity' => round($totalPerimeter * $sealantFactor, 3), 'unit' => 'm', 'cost_category' => 'Materiaal', 'source_reference' => (string) $positionId];
    $lines[] = ['key' => 'setting_blocks', 'description' => sprintf('Stel- en steunblokjes glaspositie %s', $code), 'quantity' => round($quantity * $blocksPerPane, 2), 'unit' => 'set', 'cost_category' => 'Materiaal', 'source_reference' => (string) $positionId];
    if ($totalWeight > 0) $lines[] = ['key' => 'handling', 'description' => sprintf('Handling / hijshulp glaspositie %s', $code), 'quantity' => $totalWeight, 'unit' => 'kg', 'cost_category' => 'Materieel', 'source_reference' => (string) $positionId];
    return $lines;
  }

  /** @param array<string,mixed> $context */
  private function fallbackWaste(array $context): float {
    if ($context['application_type'] === 'fire_separation' || str_contains((string) $context['glass_type'], 'fire')) return 8.0;
    if (str_contains((string) $context['glass_type'], 'laminated') || str_contains((string) $context['glass_type'], 'gelaagd')) return 6.0;
    if (str_contains((string) $context['glass_type'], 'insulated') || str_contains((string) $context['glass_type'], 'isolatie')) return 5.0;
    return 3.0;
  }

}
