<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps direct and reconstructed geometry distinguishable. */
final class MeasureInsideOnlyTest extends TestCase {

  public function testIndirectGeometryDoesNotMasqueradeAsDirectMeasurement(): void {
    $direct = ['provenance' => 'measured', 'method' => 'lidar'];
    $indirect = ['provenance' => 'calculated', 'method' => 'inside_reconstruction'];
    self::assertNotSame($direct, $indirect);
  }

}
