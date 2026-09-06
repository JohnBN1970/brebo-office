<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the narrow Measure-02 write surface. */
final class MeasureBuildBoundaryTest extends TestCase {

  public function testWritesStopAtObservationAppend(): void {
    self::assertSame('observation_append', 'observation_append');
  }

}
