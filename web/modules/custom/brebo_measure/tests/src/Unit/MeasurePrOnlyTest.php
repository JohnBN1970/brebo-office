<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** PR-only marker. */
final class MeasurePrOnlyTest extends TestCase {

  public function testOnlyPr(): void {
    self::assertTrue(TRUE);
  }

}
