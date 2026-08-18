<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit;

use Drupal\brebo_glass\Service\GlassWindLoadCalculator;
use PHPUnit\Framework\TestCase;

final class GlassWindLoadCalculatorTest extends TestCase {

  public function testVerifiedWindCheckPassesBelowUnity(): void {
    $result = (new GlassWindLoadCalculator())->calculate(0.8, -1.2, 0.2, 1.5, 2.5, 'EC1/NB-2026', 'WIND-001', TRUE);
    self::assertSame(1.68, $result['design_pressure_kpa']);
    self::assertSame(0.672, $result['utilization']);
    self::assertSame('passed', $result['state']);
  }

  public function testOverloadedGlassIsBlocked(): void {
    $result = (new GlassWindLoadCalculator())->calculate(1.0, -1.4, 0.2, 1.5, 2.0, 'EC1/NB-2026', 'WIND-002', TRUE);
    self::assertSame('blocked', $result['state']);
    self::assertGreaterThan(1.0, $result['utilization']);
  }

  public function testUnverifiedCalculationIsBlocked(): void {
    $result = (new GlassWindLoadCalculator())->calculate(0.5, -0.8, 0.2, 1.5, 2.0, 'EC1/NB-2026', 'WIND-003', FALSE);
    self::assertSame('blocked', $result['state']);
  }

  public function testMissingNormReferenceIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new GlassWindLoadCalculator())->calculate(0.5, -0.8, 0.2, 1.5, 2.0, '', 'WIND-004', TRUE);
  }

}
