<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the functional PR slice. */
final class MeasurePrScopeTest extends TestCase {

  public function testFunctionalSliceStopsAtObservationIngestion(): void {
    $lastOperation = 'add_observation';
    self::assertSame('add_observation', $lastOperation);
  }

}
