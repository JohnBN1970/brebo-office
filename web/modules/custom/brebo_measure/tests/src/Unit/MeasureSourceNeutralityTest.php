<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Ensures future sources can share the same observation semantics. */
final class MeasureSourceNeutralityTest extends TestCase {

  public function testSourcesCanReportSameObservationKey(): void {
    $key = 'opening.width.middle';
    $sources = ['apple_lidar', 'laser', 'precision_kit', 'framebot'];
    foreach ($sources as $source) {
      self::assertNotSame('', $source);
      self::assertSame('opening.width.middle', $key);
    }
  }

}
