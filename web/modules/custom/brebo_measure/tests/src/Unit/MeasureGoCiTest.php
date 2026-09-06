<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** CI handoff marker. */
final class MeasureGoCiTest extends TestCase {

  public function testCiNext(): void {
    self::assertTrue(TRUE);
  }

}
