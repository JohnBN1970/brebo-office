<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review access is not accidentally tied to submission access. */
final class IntakeReviewDedicatedPermissionTest extends UnitTestCase {

  public function testReviewRouteDoesNotUseSubmitPermission(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    $review = substr($routing, (int) strpos($routing, 'brebo_data_intake.review:'));
    self::assertStringContainsString("review brebo office intake", $review);
    self::assertStringNotContainsString("submit brebo office intake", $review);
  }

}
