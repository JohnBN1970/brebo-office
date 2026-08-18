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

  public function testLaminatedGlassLayersAreNotParsedAsDecimals(): void {
    $result = (new GlassSpecificationCalculator())->calculate(1000, 1000, 1, '44.2-15-6');
    self::assertSame(35.0, $result['estimated_weight_kg']);
    self::assertSame([], $result['warnings']);
  }

  public function testHandlingWarningUsesWeightPerPaneInsteadOfOrderTotal(): void {
    $result = (new GlassSpecificationCalculator())->calculate(1000, 1000, 10, '4-16-4');
    self::assertSame(200.0, $result['estimated_weight_kg']);
    self::assertSame([], $result['warnings']);
  }

  public function testUnknownCompositionDoesNotProduceFalseWeight(): void {
    $result = (new GlassSpecificationCalculator())->calculate(1000, 1000, 1, 'onbekend');
    self::assertSame(0.0, $result['estimated_weight_kg']);
    self::assertCount(1, $result['warnings']);
  }

  public function testInvalidDimensionsAreRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new GlassSpecificationCalculator())->calculate(0, 800, 1, '4-16-4');
  }

}
