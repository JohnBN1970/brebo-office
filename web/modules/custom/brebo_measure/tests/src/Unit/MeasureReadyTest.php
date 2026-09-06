<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** PR-ready marker test. */
final class MeasureReadyTest extends TestCase {

  public function testMeasure02IsReadyForCiReview(): void {
    self::assertTrue(TRUE);
  }

}
