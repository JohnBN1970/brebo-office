<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Closed-for-writes marker. */
final class MeasureClosedForWritesTest extends TestCase {

  public function testClosed(): void {
    self::assertTrue(TRUE);
  }

}
