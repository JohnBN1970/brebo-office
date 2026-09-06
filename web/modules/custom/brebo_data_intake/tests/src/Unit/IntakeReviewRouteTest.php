<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review workbench owns the canonical intake landing URL. */
final class IntakeReviewRouteTest extends UnitTestCase {

  public function testCanonicalIntakeRoute(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertStringContainsString("brebo_data_intake.review:\n  path: '/brebo-office/intake'", $routing);
    self::assertStringContainsString("brebo_data_intake.upload:\n  path: '/brebo-office/intake/upload'", $routing);
  }

}
