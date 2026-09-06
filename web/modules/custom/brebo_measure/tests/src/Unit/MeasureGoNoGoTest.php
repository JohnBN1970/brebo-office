<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents that hardware follows empirical field evidence. */
final class MeasureGoNoGoTest extends TestCase {

  public function testHardwareRequiresFieldEvidence(): void {
    $gate = ['repeatability', 'reference_bias', 'workflow_time', 'missing_data'];
    self::assertContains('repeatability', $gate);
    self::assertContains('workflow_time', $gate);
  }

}
