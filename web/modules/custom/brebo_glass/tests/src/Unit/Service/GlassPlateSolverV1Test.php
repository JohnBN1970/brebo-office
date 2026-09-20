<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit\Service;

use Drupal\brebo_glass\Service\GlassPlateSolverV1;
use PHPUnit\Framework\TestCase;

final class GlassPlateSolverV1Test extends TestCase {

  public function testMissingTraceabilityFailsClosed(): void {
    $result = (new GlassPlateSolverV1())->solve([]);
    self::assertSame('blocked', $result['status']);
    self::assertFalse($result['validated']);
    self::assertFalse($result['production_release']);
  }

  public function testPrototypeCalculationUsesSuppliedCoefficients(): void {
    $result = (new GlassPlateSolverV1())->solve([
      'width_mm' => 1000,
      'height_mm' => 1200,
      'thickness_mm' => 6,
      'design_pressure_kpa' => 1.0,
      'youngs_modulus_mpa' => 70000,
      'poisson_ratio' => 0.23,
      'deflection_coefficient' => 0.004,
      'stress_coefficient' => 0.30,
      'coefficient_source' => 'verified-reference-set',
      'coefficient_source_version' => 'v1',
      'material_source' => 'verified-material-set',
      'material_source_version' => 'v1',
      'support' => 'four_sided',
      'load_case' => 'uniform_lateral_pressure',
    ]);

    self::assertSame('prototype_result', $result['status']);
    self::assertGreaterThan(0.0, $result['results']['deflection_mm']);
    self::assertGreaterThan(0.0, $result['results']['stress_mpa']);
    self::assertFalse($result['validated']);
    self::assertFalse($result['production_release']);
  }

  public function testInvalidCoefficientsAreRejected(): void {
    $result = (new GlassPlateSolverV1())->solve([
      'width_mm' => 1000,
      'height_mm' => 1200,
      'thickness_mm' => 6,
      'design_pressure_kpa' => 1.0,
      'youngs_modulus_mpa' => 70000,
      'poisson_ratio' => 0.23,
      'deflection_coefficient' => 0.0,
      'stress_coefficient' => 0.30,
      'coefficient_source' => 'verified-reference-set',
      'coefficient_source_version' => 'v1',
      'material_source' => 'verified-material-set',
      'material_source_version' => 'v1',
      'support' => 'four_sided',
      'load_case' => 'uniform_lateral_pressure',
    ]);

    self::assertSame('blocked', $result['status']);
    self::assertSame('invalid_coefficients', $result['gate']);
  }

}
