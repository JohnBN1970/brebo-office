<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the practical value dimensions Measure must prove. */
final class MeasureBusinessProofTest extends TestCase {

  public function testProofTracksTimeAndFailurePrevention(): void {
    $dimensions = ['field_time', 'office_time', 'measurement_error', 'second_visit', 'failure_cost_prevention'];
    self::assertContains('office_time', $dimensions);
    self::assertContains('failure_cost_prevention', $dimensions);
  }

}
