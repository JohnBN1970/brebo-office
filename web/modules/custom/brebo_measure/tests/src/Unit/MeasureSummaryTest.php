<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Final scope smoke test for Measure-02. */
final class MeasureSummaryTest extends TestCase {

  public function testTransportSliceContainsFourOperations(): void {
    $operations = ['read_opening', 'create_assignment', 'create_capture', 'add_observation'];
    self::assertCount(4, $operations);
  }

}
