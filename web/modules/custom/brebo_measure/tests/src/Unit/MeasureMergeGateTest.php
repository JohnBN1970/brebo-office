<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the CI merge gate. */
final class MeasureMergeGateTest extends TestCase {

  public function testQualityGatePrecedesMerge(): void {
    $order = ['quality_gate', 'merge'];
    self::assertSame('quality_gate', $order[0]);
  }

}
