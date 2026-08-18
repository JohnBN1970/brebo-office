<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit;

use Drupal\brebo_glass\Service\GlassSpecificationCalculator;
use PHPUnit\Framework\TestCase;

final class GlassSpecificationCalculatorTest extends TestCase {

  public function testInsulatingGlassCalculation(): void {
    $result = (new GlassSpecificationCalculator())->calculate(1200, 800, 3, '4-16-4');
    self::assertSame(2.88, $result['area_m2']);
    self::assertSame(12.0, $result['perimeter_m']);
    self::assertSame(57.6, $result['estimated_weight_kg']);
    self::assertSame([], $result['warnings']);
  }

  public function testInvalidDimensionsAreRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new GlassSpecificationCalculator())->calculate(0, 800, 1, '4-16-4');
  }

}

