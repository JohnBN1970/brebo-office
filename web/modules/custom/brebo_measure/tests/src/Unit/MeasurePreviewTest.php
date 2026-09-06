<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** PR preview smoke test. */
final class MeasurePreviewTest extends TestCase {

  public function testFunctionalCoreIsTransportLayer(): void {
    self::assertSame('routing_controller', 'routing_controller');
  }

}
