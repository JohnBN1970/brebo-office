<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit\Service;

use Drupal\brebo_glass\Service\GlassEngineV1;
use PHPUnit\Framework\TestCase;

final class GlassEngineV1Test extends TestCase {

  public function testIncompleteInputIsBlocked(): void {
    $result = (new GlassEngineV1())->evaluate([]);
    self::assertSame('blocked', $result['status']);
    self::assertSame('incomplete_input', $result['gate']);
    self::assertFalse($result['validated']);
    self::assertFalse($result['production_release']);
    self::assertNull($result['results']['recommended_composition']);
  }

  public function testCompleteInputStillRequiresValidation(): void {
    $result = (new GlassEngineV1())->evaluate([
      'width_mm' => 1200,
      'height_mm' => 1500,
      'glass_type' => 'insulating_double',
      'orientation_deg' => 90,
      'support' => 'four_sided',
      'application' => 'standard',
      'load_case' => 'wind',
      'design_pressure_kpa' => 1.2,
      'design_pressure_source' => 'verified_reference_case',
      'norm_reference' => 'validation_pending',
      'candidate_composition' => 'example-only-not-released',
    ]);

    self::assertSame('validation_required', $result['status']);
    self::assertSame('calculation_method_not_validated', $result['gate']);
    self::assertFalse($result['validated']);
    self::assertFalse($result['production_release']);
    self::assertNull($result['results']['stress_mpa']);
    self::assertNull($result['results']['deflection_mm']);
    self::assertNull($result['results']['utilisation_ratio']);
    self::assertNull($result['results']['recommended_composition']);
  }

  public function testNegativeDesignPressureIsRejected(): void {
    $result = (new GlassEngineV1())->evaluate([
      'width_mm' => 1200,
      'height_mm' => 1500,
      'glass_type' => 'float',
      'orientation_deg' => 90,
      'support' => 'four_sided',
      'application' => 'standard',
      'load_case' => 'wind',
      'design_pressure_kpa' => -1,
      'design_pressure_source' => 'source',
      'norm_reference' => 'reference',
    ]);

    self::assertSame('blocked', $result['status']);
    self::assertSame('invalid_input', $result['gate']);
    self::assertFalse($result['production_release']);
  }

}
