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
    $weightPerPane = (float) ($position['estimated_weight_kg'] ?? 0.0);
    $composition = trim((string) ($position['composition'] ?? ''));
    if ($positionId <= 0 || $code === '' || $quantity <= 0 || $areaPerPane <= 0) {
      throw new \InvalidArgumentException('Vrijgegeven glaspositie mist calculatiegegevens.');
    }

    $totalArea = round($areaPerPane * $quantity, 4);
    $totalWeight = round(max(0.0, $weightPerPane) * $quantity, 2);
    $label = $composition !== '' ? sprintf('%s - %s', $code, $composition) : $code;

    $lines = [
      [
        'key' => 'glass',
        'description' => sprintf('Glas %s', $label),
        'quantity' => $totalArea,
        'unit' => 'm2',
        'cost_category' => 'Materiaal',
        'source_reference' => (string) $positionId,
      ],
      [
        'key' => 'installation',
        'description' => sprintf('Montage glaspositie %s', $code),
        'quantity' => $quantity,
        'unit' => 'st',
        'cost_category' => 'Arbeid',
        'source_reference' => (string) $positionId,
      ],
    ];

    if ($totalWeight > 0) {
      $lines[] = [
        'key' => 'handling',
        'description' => sprintf('Handling glaspositie %s', $code),
        'quantity' => $totalWeight,
        'unit' => 'kg',
        'cost_category' => 'Materieel',
        'source_reference' => (string) $positionId,
      ];
    }

    return $lines;
  }

}
