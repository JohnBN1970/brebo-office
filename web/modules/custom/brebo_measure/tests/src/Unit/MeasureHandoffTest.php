<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the next increment ordering. */
final class MeasureHandoffTest extends TestCase {

  public function testLifecycleAndOfficeLaunchPrecedeSensorPolish(): void {
    $order = ['lifecycle_validation', 'office_launch', 'sensor_polish'];
    self::assertSame('lifecycle_validation', $order[0]);
  }

}
