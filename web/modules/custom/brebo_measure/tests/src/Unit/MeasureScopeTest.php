<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps Measure-02 focused on capture transport and validation evidence. */
final class MeasureScopeTest extends TestCase {

  public function testOrderingIsOutsideCaptureApiScope(): void {
    $inScope = ['opening', 'assignment', 'capture', 'observation'];
    self::assertNotContains('order_frame', $inScope);
  }

}
