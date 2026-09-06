<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the evidence-first hardware decision. */
final class MeasureRoadmapTest extends TestCase {

  public function testHardwareDecisionFollowsFieldValidation(): void {
    $order = ['office_api', 'mobile_proof', 'field_validation', 'hardware_decision'];
    self::assertGreaterThan(array_search('field_validation', $order, TRUE), array_search('hardware_decision', $order, TRUE));
  }

}
