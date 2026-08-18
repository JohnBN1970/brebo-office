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

    $paneArea = ($widthMm / 1000) * ($heightMm / 1000);
    $area = $paneArea * $quantity;
    $perimeter = 2 * (($widthMm + $heightMm) / 1000) * $quantity;
    $glassThickness = $this->glassThicknessFromComposition($composition);
    $paneWeight = $paneArea * $glassThickness * self::GLASS_DENSITY_KG_M2_MM;
    $weight = $paneWeight * $quantity;
    $warnings = [];

    if ($paneArea > 6.0) {
      $warnings[] = 'Grote glasruit: controleer maakbaarheid, handling, transport en montagevoorzieningen.';
    }
    if ($paneWeight > 150.0) {
      $warnings[] = 'Hoog gewicht per ruit: hijs- en bezettingsplan verplicht verifiëren.';
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
    $layers = preg_split('/\s*-\s*/', trim($composition));
    if (!$layers) {
      return 0.0;
    }

    $thickness = 0.0;
    foreach ($layers as $index => $layer) {
      // Conventional insulating-glass notation alternates glass and cavity.
      if ($index % 2 !== 0) {
        continue;
      }

      $parsed = $this->glassLayerThickness($layer);
      if ($parsed === NULL) {
        return 0.0;
      }
      $thickness += $parsed;
    }

    return $thickness;
  }

  /**
   * Parses plain and common laminated-glass notation (for example 44.2).
   */
  private function glassLayerThickness(string $layer): ?float {
    $layer = trim(str_replace(',', '.', $layer));

    if (preg_match('/^(\d)(\d)\.[1-9]$/', $layer, $matches)) {
      return (float) ((int) $matches[1] + (int) $matches[2]);
    }

    if (preg_match('/^\d+(?:\.\d+)?$/', $layer)) {
      return (float) $layer;
    }

    return NULL;
  }

}
