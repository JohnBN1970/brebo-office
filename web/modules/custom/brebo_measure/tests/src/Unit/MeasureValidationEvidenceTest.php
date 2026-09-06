<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps validation evidence non-destructive and comparable. */
final class MeasureValidationEvidenceTest extends TestCase {

  public function testDifferenceIsDerivedFromTwoRetainedObservations(): void {
    $lidar = ['value_mm' => 1843.0, 'method' => 'lidar'];
    $reference = ['value_mm' => 1841.0, 'method' => 'laser'];
    $difference = $lidar['value_mm'] - $reference['value_mm'];
    self::assertSame(2.0, $difference);
    self::assertSame('lidar', $lidar['method']);
    self::assertSame('laser', $reference['method']);
  }

}
