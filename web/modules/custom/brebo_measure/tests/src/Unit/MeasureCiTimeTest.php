<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** CI-time marker. */
final class MeasureCiTimeTest extends TestCase {

  public function testCiTime(): void {
    self::assertTrue(TRUE);
  }

}
