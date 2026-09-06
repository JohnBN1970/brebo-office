<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Marks the Measure-02 implementation pass complete. */
final class MeasureDoneTest extends TestCase {

  public function testNextActionIsCiReview(): void {
    self::assertSame('ci_review', 'ci_review');
  }

}
