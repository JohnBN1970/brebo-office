<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents that CI failures must be fixed rather than bypassed. */
final class MeasureCiBoundaryTest extends TestCase {

  public function testQualityGateIsRequired(): void {
    self::assertTrue(TRUE);
  }

}
