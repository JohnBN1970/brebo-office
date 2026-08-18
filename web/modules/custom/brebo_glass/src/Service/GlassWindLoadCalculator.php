<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Calculates and checks design wind pressure from traceable norm inputs.
 */
final class GlassWindLoadCalculator {

  public function designPressure(
    float $peakVelocityPressureKpa,
    float $externalPressureCoefficient,
    float $internalPressureCoefficient,
    float $partialFactor,
  ): float {
    if ($peakVelocityPressureKpa <= 0 || $partialFactor <= 0) {
      throw new \InvalidArgumentException('Winddruk en veiligheidsfactor moeten groter zijn dan nul.');
    }
    $netCoefficient = abs($externalPressureCoefficient - $internalPressureCoefficient);
    if ($netCoefficient <= 0) {
      throw new \InvalidArgumentException('Het netto drukverschil moet groter zijn dan nul.');
    }
    return round($peakVelocityPressureKpa * $netCoefficient * $partialFactor, 3);
  }

  /**
   * @return array{design_pressure_kpa: float, utilization: float, state: string, issues: string[]}
   */
  public function calculate(
    float $peakVelocityPressureKpa,
    float $externalPressureCoefficient,
    float $internalPressureCoefficient,
    float $partialFactor,
    float $glassResistanceKpa,
    string $standardReference,
    string $calculationReference,
    bool $verified,
  ): array {
    if ($peakVelocityPressureKpa <= 0 || $partialFactor <= 0 || $glassResistanceKpa <= 0) {
      throw new \InvalidArgumentException('Winddruk, veiligheidsfactor en glasweerstand moeten groter zijn dan nul.');
    }
    if (trim($standardReference) === '' || trim($calculationReference) === '') {
      throw new \InvalidArgumentException('Normversie en bronberekening zijn verplicht.');
    }

    $netCoefficient = abs($externalPressureCoefficient - $internalPressureCoefficient);
    if ($netCoefficient <= 0) {
      throw new \InvalidArgumentException('Het netto drukverschil moet groter zijn dan nul.');
    }

    $designPressure = $this->designPressure($peakVelocityPressureKpa, $externalPressureCoefficient, $internalPressureCoefficient, $partialFactor);
    $utilization = $designPressure / $glassResistanceKpa;
    $issues = [];

    if ($utilization > 1.0) {
      $issues[] = 'Ontwerpwinddruk is groter dan de onderbouwde glasweerstand.';
    }
    if (!$verified) {
      $issues[] = 'Windbelastingberekening is nog niet door een bevoegde deskundige geverifieerd.';
    }

    return [
      'design_pressure_kpa' => round($designPressure, 3),
      'utilization' => round($utilization, 3),
      'state' => $utilization > 1.0 || !$verified ? 'blocked' : 'passed',
      'issues' => $issues,
    ];
  }

}
