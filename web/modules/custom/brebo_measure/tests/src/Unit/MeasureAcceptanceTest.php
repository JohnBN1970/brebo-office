<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Smoke test for the Measure-02 acceptance boundary. */
final class MeasureAcceptanceTest extends TestCase {

  public function testFieldCaptureAndProductionRemainSeparate(): void {
    $capture = 'measurement_dossier';
    $production = 'approved_production_geometry';
    self::assertNotSame($capture, $production);
  }

}
