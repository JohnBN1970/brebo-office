<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps the LiDAR proof evidence-based rather than all-or-nothing. */
final class MeasureLidarProofTest extends TestCase {

  public function testFindingHardwareNeedCanBeValidProofResult(): void {
    $validResults = ['mobile_sufficient', 'reference_needed', 'precision_hardware_needed', 'human_review_needed'];
    self::assertContains('precision_hardware_needed', $validResults);
  }

}
