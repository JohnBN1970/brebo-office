<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Converts six source measurements into a traceable order size.
 */
final class GlassThreePointMeasurementCalculator {

  /**
   * @param int[] $widths
   * @param int[] $heights
   *
   * @return array{width_mm: int, height_mm: int, width_spread_mm: int, height_spread_mm: int, warnings: string[]}
   */
  public function calculate(array $widths, array $heights, int $widthDeductionMm, int $heightDeductionMm): array {
    $this->assertMeasurements($widths, 'breedte');
    $this->assertMeasurements($heights, 'hoogte');

    if ($widthDeductionMm < 0 || $heightDeductionMm < 0) {
      throw new \InvalidArgumentException('Aftrekmaat mag niet negatief zijn.');
    }

    $minimumWidth = min($widths);
    $minimumHeight = min($heights);
    $width = $minimumWidth - $widthDeductionMm;
    $height = $minimumHeight - $heightDeductionMm;

    if ($width <= 0 || $height <= 0) {
      throw new \InvalidArgumentException('De aftrekmaat is gelijk aan of groter dan de kleinste sponningmaat.');
    }

    $widthSpread = max($widths) - $minimumWidth;
    $heightSpread = max($heights) - $minimumHeight;
    $warnings = [];

    if ($widthSpread > 3) {
      $warnings[] = 'Breedtematen wijken meer dan 3 mm af; controleer scheefstand, opname en toepasbaarheid.';
    }
    if ($heightSpread > 3) {
      $warnings[] = 'Hoogtematen wijken meer dan 3 mm af; controleer scheefstand, opname en toepasbaarheid.';
    }
    if ($widthDeductionMm === 0 || $heightDeductionMm === 0) {
      $warnings[] = 'Voor minimaal één richting is geen aftrekmaat vastgelegd; verifieer de benodigde omtrekspeling.';
    }

    return [
      'width_mm' => $width,
      'height_mm' => $height,
      'width_spread_mm' => $widthSpread,
      'height_spread_mm' => $heightSpread,
      'warnings' => $warnings,
    ];
  }

  /**
   * @param int[] $measurements
   */
  private function assertMeasurements(array $measurements, string $label): void {
    if (count($measurements) !== 3) {
      throw new \InvalidArgumentException(sprintf('Voor %s zijn exact drie metingen verplicht.', $label));
    }

    foreach ($measurements as $measurement) {
      if ($measurement <= 0) {
        throw new \InvalidArgumentException(sprintf('Alle %smetingen moeten groter zijn dan nul.', $label));
      }
    }
  }

}
