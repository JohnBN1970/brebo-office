<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the minimum practical Measure workflow. */
final class MeasureWorkflowContractTest extends TestCase {

  public function testFirstFieldWorkflowOrder(): void {
    $workflow = ['opening', 'assignment', 'capture', 'observation', 'validation'];
    self::assertSame('opening', $workflow[0]);
    self::assertSame('capture', $workflow[2]);
    self::assertSame('validation', $workflow[4]);
  }

  public function testReferenceMeasurementCanCoexistWithLidar(): void {
    $observations = [
      ['key' => 'opening.width.middle', 'method' => 'lidar', 'value_mm' => 1843],
      ['key' => 'opening.width.middle', 'method' => 'laser', 'value_mm' => 1841],
    ];
    self::assertCount(2, $observations);
    self::assertSame(2, abs($observations[0]['value_mm'] - $observations[1]['value_mm']));
  }

}
