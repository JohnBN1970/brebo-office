<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Ensures sensor and reference values remain independently auditable. */
final class MeasureReferenceComparisonTest extends TestCase {

  public function testSignedDifferenceCanBeCalculatedWithoutOverwritingSources(): void {
    $lidar = 1843.0;
    $reference = 1841.0;
    self::assertSame(2.0, $lidar - $reference);
    self::assertSame(1843.0, $lidar);
    self::assertSame(1841.0, $reference);
  }

}
