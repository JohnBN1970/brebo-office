<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies there is one canonical intake review landing route. */
final class IntakeReviewNoDuplicateRouteTest extends UnitTestCase {

  public function testReviewRouteDeclaredOnce(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertSame(1, substr_count($routing, "path: '/brebo-office/intake'\n"));
  }

}
