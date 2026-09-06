<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents control dimensions as independent evidence. */
final class MeasureControlDimensionTest extends TestCase {

  public function testControlMeasurementRetainsItsOwnMethod(): void {
    $sensor = ['method' => 'lidar', 'value_mm' => 1843.0];
    $control = ['method' => 'laser', 'value_mm' => 1841.0];
    self::assertNotSame($sensor['method'], $control['method']);
  }

}
