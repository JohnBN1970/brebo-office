<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit;

use Drupal\brebo_glass\Service\GlassThreePointMeasurementCalculator;
use PHPUnit\Framework\TestCase;

final class GlassThreePointMeasurementCalculatorTest extends TestCase {

  public function testSmallestMeasurementsDetermineOrderSize(): void {
    $result = (new GlassThreePointMeasurementCalculator())->calculate(
      [1012, 1010, 1011],
      [1215, 1212, 1214],
      10,
      10,
    );

    self::assertSame(1000, $result['width_mm']);
    self::assertSame(1202, $result['height_mm']);
    self::assertSame(2, $result['width_spread_mm']);
    self::assertSame(3, $result['height_spread_mm']);
    self::assertSame([], $result['warnings']);
  }

  public function testLargeSpreadProducesControlWarning(): void {
    $result = (new GlassThreePointMeasurementCalculator())->calculate(
      [1000, 1005, 1010],
      [1200, 1200, 1200],
      8,
      8,
    );

    self::assertSame(992, $result['width_mm']);
    self::assertCount(1, $result['warnings']);
  }

  public function testExactlyThreeMeasurementsAreRequired(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new GlassThreePointMeasurementCalculator())->calculate([1000, 1000], [1200, 1200, 1200], 8, 8);
  }

  public function testDeductionCannotConsumeSmallestMeasurement(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new GlassThreePointMeasurementCalculator())->calculate([10, 10, 10], [20, 20, 20], 10, 5);
  }

}
