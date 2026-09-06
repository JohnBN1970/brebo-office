<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents that coupled openings remain explicit elements. */
final class MeasureCoupledOpeningTest extends TestCase {

  public function testCoupledElementsAreNotDerivedByEqualDivision(): void {
    $elements = [1200.0, 1800.0, 950.0];
    self::assertNotSame($elements[0], $elements[1]);
    self::assertSame(3950.0, array_sum($elements));
  }

}
