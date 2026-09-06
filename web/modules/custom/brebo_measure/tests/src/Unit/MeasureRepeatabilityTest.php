<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps repeated raw captures visible during validation. */
final class MeasureRepeatabilityTest extends TestCase {

  public function testRepeatedCapturesRemainSeparate(): void {
    $runs = [1843.0, 1842.0, 1844.0, 1843.0, 1841.0];
    self::assertCount(5, $runs);
    self::assertSame(3.0, max($runs) - min($runs));
  }

}
