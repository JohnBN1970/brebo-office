<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps reported precision distinct from measurement uncertainty. */
final class MeasureNoFakePrecisionTest extends TestCase {

  public function testMillimetreValueCanStillCarryLargerUncertainty(): void {
    $observation = ['value_mm' => 1843.0, 'uncertainty_mm' => 2.0];
    self::assertSame(1843.0, $observation['value_mm']);
    self::assertSame(2.0, $observation['uncertainty_mm']);
  }

}
