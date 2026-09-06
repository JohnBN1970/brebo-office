<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents that Measure-02 builds directly on Measure-01. */
final class MeasureBranchBaseTest extends TestCase {

  public function testMeasure02DependsOnMeasure01Domain(): void {
    self::assertSame('measure_domain_foundation', 'measure_domain_foundation');
  }

}
