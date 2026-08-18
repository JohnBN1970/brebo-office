<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit;

use Drupal\brebo_glass\Service\GlassCandidateSelector;
use PHPUnit\Framework\TestCase;

final class GlassCandidateSelectorTest extends TestCase {

  public function testLightestSuitableCandidateIsRecommended(): void {
    $result = (new GlassCandidateSelector())->select([
      ['id' => '4-16-4', 'glass_type' => 'insulating', 'wind_resistance_kpa' => 2.0, 'max_width_mm' => 1600, 'max_height_mm' => 2200, 'weight_kg_m2' => 20.0, 'verified' => TRUE],
      ['id' => '6-16-6', 'glass_type' => 'insulating', 'wind_resistance_kpa' => 3.5, 'max_width_mm' => 2400, 'max_height_mm' => 3000, 'weight_kg_m2' => 30.0, 'verified' => TRUE],
    ], 1.8, 1200, 1800, 'standard');

    self::assertSame('4-16-4', $result['recommended']['id']);
  }

  public function testInsufficientCandidateIsRejected(): void {
    $result = (new GlassCandidateSelector())->select([
      ['id' => '4-16-4', 'glass_type' => 'insulating', 'wind_resistance_kpa' => 1.5, 'max_width_mm' => 1600, 'max_height_mm' => 2200, 'weight_kg_m2' => 20.0, 'verified' => TRUE],
    ], 2.0, 1200, 1800, 'standard');

    self::assertNull($result['recommended']);
    self::assertCount(1, $result['rejected']);
  }

  public function testFallProtectionRejectsNonLaminatedGlass(): void {
    $result = (new GlassCandidateSelector())->select([
      ['id' => '6-16-6', 'glass_type' => 'insulating', 'wind_resistance_kpa' => 3.5, 'max_width_mm' => 2400, 'max_height_mm' => 3000, 'weight_kg_m2' => 30.0, 'verified' => TRUE],
      ['id' => '66.2', 'glass_type' => 'laminated', 'wind_resistance_kpa' => 3.0, 'max_width_mm' => 2000, 'max_height_mm' => 2800, 'weight_kg_m2' => 30.0, 'verified' => TRUE],
    ], 2.0, 1200, 1800, 'fall_protection');

    self::assertSame('66.2', $result['recommended']['id']);
  }

}
