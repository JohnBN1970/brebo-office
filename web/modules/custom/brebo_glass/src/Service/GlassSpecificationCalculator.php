<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Performs traceable glass quantity and plausibility calculations.
 */
final class GlassSpecificationCalculator {

  private const GLASS_DENSITY_KG_M2_MM = 2.5;

  /**
   * Calculates quantities without treating automated input as verified.
   *
   * @return array{area_m2: float, perimeter_m: float, estimated_weight_kg: float, warnings: string[]}
   */
  public function calculate(int $widthMm, int $heightMm, int $quantity, string $composition): array {
    if ($widthMm <= 0 || $heightMm <= 0 || $quantity <= 0) {
      throw new \InvalidArgumentException('Breedte, hoogte en aantal moeten groter zijn dan nul.');
    }

    $area = ($widthMm / 1000) * ($heightMm / 1000) * $quantity;
    $perimeter = 2 * (($widthMm + $heightMm) / 1000) * $quantity;
    $glassThickness = $this->glassThicknessFromComposition($composition);
    $weight = $area * $glassThickness * self::GLASS_DENSITY_KG_M2_MM;
    $warnings = [];

    if ($area > 12.0) {
      $warnings[] = 'Groot glasoppervlak: controleer handling, transport en montagevoorzieningen.';
    }
    if ($weight > 150.0) {
      $warnings[] = 'Hoog berekend gewicht: hijs- en bezettingsplan verplicht verifiëren.';
    }
    if ($glassThickness === 0.0) {
      $warnings[] = 'Glasdikte kon niet betrouwbaar uit de opbouw worden afgeleid; gewicht is nog niet bepaald.';
    }

    return [
      'area_m2' => round($area, 3),
      'perimeter_m' => round($perimeter, 3),
      'estimated_weight_kg' => round($weight, 2),
      'warnings' => $warnings,
    ];
  }

  /**
   * Adds only glass layers; cavity widths are excluded from weight.
   */
  private function glassThicknessFromComposition(string $composition): float {
    if (!preg_match_all('/(?<!\d)(\d+(?:[.,]\d+)?)(?!\d)/', $composition, $matches)) {
      return 0.0;
    }

    $values = array_map(static fn(string $value): float => (float) str_replace(',', '.', $value), $matches[1]);
    if (count($values) === 1) {
      return $values[0];
    }

    // Conventional insulating-glass notation alternates glass and cavity.
    return array_sum(array_filter($values, static fn(float $value, int $index): bool => $index % 2 === 0, ARRAY_FILTER_USE_BOTH));
  }

}

