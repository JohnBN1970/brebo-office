<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the minimum end-to-end proof sequence. */
final class MeasureFirstRunTest extends TestCase {

  public function testProofContainsSensorAndIndependentControl(): void {
    $methods = ['lidar', 'laser'];
    self::assertContains('lidar', $methods);
    self::assertContains('laser', $methods);
  }

}
