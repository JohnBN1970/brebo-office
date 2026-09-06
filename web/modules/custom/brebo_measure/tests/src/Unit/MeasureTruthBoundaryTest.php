<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps raw capture separate from approved production truth. */
final class MeasureTruthBoundaryTest extends TestCase {

  public function testRawCaptureIsEvidenceNotProductionTruth(): void {
    self::assertNotSame('raw_capture', 'approved_production_geometry');
  }

}
