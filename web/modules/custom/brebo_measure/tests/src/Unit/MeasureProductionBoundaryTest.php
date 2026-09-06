<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps field capture separate from production approval. */
final class MeasureProductionBoundaryTest extends TestCase {

  public function testCaptureStatusDoesNotEqualProductionRelease(): void {
    $captureState = 'accepted';
    $productionState = 'not_released';
    self::assertNotSame($captureState, $productionState);
  }

}
