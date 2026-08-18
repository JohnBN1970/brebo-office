<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/** Converts an approved glass position into calculation-ready quantities. */
final class GlassCalculationLineFactory {

  /**
   * @param array<string, mixed> $position
   * @return array<int, array<string, mixed>>
   */
  public function build(array $position): array {
    if ((string) ($position['technical_status'] ?? '') !== 'approved') {
      throw new \InvalidArgumentException('Alleen technisch vrijgegeven glasposities mogen naar de calculatie.');
    }

    $positionId = (int) ($position['id'] ?? 0);
    $code = trim((string) ($position['position_code'] ?? ''));
    $quantity = (int) ($position['quantity'] ?? 0);
    $areaPerPane = (float) ($position['area_m2'] ?? 0.0);
    $perimeterPerPane = (float) ($position['perimeter_m'] ?? 0.0);
    $weightPerPane = (float) ($position['estimated_weight_kg'] ?? 0.0);
    $composition = trim((string) ($position['composition'] ?? ''));
    if ($positionId <= 0 || $code === '' || $quantity <= 0 || $areaPerPane <= 0) {
      throw new \InvalidArgumentException('Vrijgegeven glaspositie mist calculatiegegevens.');
    }

    $netArea = round($areaPerPane * $quantity, 4);
    $wastePct = $this->wastePercentage($position);
    $purchaseArea = round($netArea * (1 + ($wastePct / 100)), 4);
    $totalPerimeter = round(max(0.0, $perimeterPerPane) * $quantity, 3);
    $totalWeight = round(max(0.0, $weightPerPane) * $quantity, 2);
    $installationHours = round($this->installationHoursPerPane($position) * $quantity, 3);
    $label = $composition !== '' ? sprintf('%s - %s', $code, $composition) : $code;

    $lines = [
      [
        'key' => 'glass',
        'description' => sprintf('Glas %s (netto %.3f m2 + %.1f%% snij-/breukverlies)', $label, $netArea, $wastePct),
        'quantity' => $purchaseArea,
        'unit' => 'm2',
        'cost_category' => 'Materiaal',
        'source_reference' => (string) $positionId,
      ],
      [
        'key' => 'installation',
        'description' => sprintf('Montage glaspositie %s', $code),
        'quantity' => $installationHours,
        'unit' => 'uur',
        'cost_category' => 'Arbeid',
        'source_reference' => (string) $positionId,
      ],
    ];

    if ($totalPerimeter > 0) {
      $lines[] = [
        'key' => 'sealant',
        'description' => sprintf('Beglazingskit / afdichting glaspositie %s', $code),
        'quantity' => round($totalPerimeter * 1.05, 3),
        'unit' => 'm',
        'cost_category' => 'Materiaal',
        'source_reference' => (string) $positionId,
      ];
    }

    $lines[] = [
      'key' => 'setting_blocks',
      'description' => sprintf('Stel- en steunblokjes glaspositie %s', $code),
      'quantity' => $quantity * 2,
      'unit' => 'set',
      'cost_category' => 'Materiaal',
      'source_reference' => (string) $positionId,
    ];

    if ($totalWeight > 0) {
      $lines[] = [
        'key' => 'handling',
        'description' => sprintf('Handling / hijshulp glaspositie %s', $code),
        'quantity' => $totalWeight,
        'unit' => 'kg',
        'cost_category' => 'Materieel',
        'source_reference' => (string) $positionId,
      ];
    }

    return $lines;
  }

  /** @param array<string,mixed> $position */
  private function wastePercentage(array $position): float {
    $type = strtolower((string) ($position['glass_type'] ?? ''));
    $application = strtolower((string) ($position['application_type'] ?? 'standard'));
    if ($application === 'fire_separation' || str_contains($type, 'fire')) return 8.0;
    if (str_contains($type, 'laminated') || str_contains($type, 'gelaagd')) return 6.0;
    if (str_contains($type, 'insulated') || str_contains($type, 'isolatie')) return 5.0;
    return 3.0;
  }

  /** @param array<string,mixed> $position */
  private function installationHoursPerPane(array $position): float {
    $area = max(0.0, (float) ($position['area_m2'] ?? 0.0));
    $weight = max(0.0, (float) ($position['estimated_weight_kg'] ?? 0.0));
    $application = (string) ($position['application_type'] ?? 'standard');
    $hours = 0.35 + ($area * 0.30);
    if ($weight > 50) $hours += 0.25;
    if ($weight > 100) $hours += 0.50;
    if (in_array($application, ['overhead', 'fall_protection', 'fire_separation'], TRUE)) $hours += 0.35;
    return round($hours, 3);
  }

}
