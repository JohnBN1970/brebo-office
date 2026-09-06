<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the canonical intake route uses the review controller. */
final class IntakeReviewRouteControllerTest extends UnitTestCase {

  public function testReviewRouteUsesReviewController(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertStringContainsString("_controller: '\\Drupal\\brebo_data_intake\\Controller\\IntakeReviewController::overview'", $routing);
  }

}
