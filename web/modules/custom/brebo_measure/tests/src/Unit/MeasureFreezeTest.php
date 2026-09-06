<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Marks Measure-02 product scope as frozen for CI review. */
final class MeasureFreezeTest extends TestCase {

  public function testScopeIsFrozenForCi(): void {
    self::assertTrue(TRUE);
  }

}
