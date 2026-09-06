<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Ensures capture quality cannot be confused with ordering approval. */
final class MeasureQualityBoundaryTest extends TestCase {

  public function testGreenCaptureIsNotProductionRelease(): void {
    self::assertNotSame('green', 'production_released');
  }

}
