<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Stop-for-CI marker. */
final class MeasureStopForCiTest extends TestCase {

  public function testCi(): void {
    self::assertTrue(TRUE);
  }

}
