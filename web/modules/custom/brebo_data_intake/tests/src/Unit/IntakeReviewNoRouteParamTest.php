<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the landing queue is source-neutral and has no source route parameter. */
final class IntakeReviewNoRouteParamTest extends UnitTestCase {

  public function testReviewRouteHasNoSourceParameter(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertStringContainsString("path: '/brebo-office/intake'", $routing);
    self::assertStringNotContainsString("/brebo-office/intake/{source}", $routing);
  }

}
