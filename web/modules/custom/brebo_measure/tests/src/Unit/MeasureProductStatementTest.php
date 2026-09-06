<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents Measure's immediate product objective. */
final class MeasureProductStatementTest extends TestCase {

  public function testImmediateGoalIsFailureAndOfficeTimeReduction(): void {
    $goals = ['measurement_failure_reduction', 'office_reentry_reduction'];
    self::assertCount(2, $goals);
  }

}
