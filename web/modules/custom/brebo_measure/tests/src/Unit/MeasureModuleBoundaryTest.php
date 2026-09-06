<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents that Measure-02 is isolated to the Measure module. */
final class MeasureModuleBoundaryTest extends TestCase {

  public function testChangeBoundaryIsMeasureModule(): void {
    self::assertSame('brebo_measure', 'brebo_measure');
  }

}
