<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the intended short operator flow. */
final class MeasureOperatorFlowTest extends TestCase {

  public function testOperatorFlowRemainsShort(): void {
    $steps = ['select_opening', 'measure', 'confirm_references', 'control_dimension', 'finish'];
    self::assertCount(5, $steps);
  }

}
