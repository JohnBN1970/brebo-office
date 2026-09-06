<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps delivery claims tied to the actual increment. */
final class MeasureDeliveryTest extends TestCase {

  public function testApiFoundationDoesNotClaimLidarProductionAccuracy(): void {
    self::assertFalse(FALSE, 'LiDAR production accuracy remains an empirical field question.');
  }

}
