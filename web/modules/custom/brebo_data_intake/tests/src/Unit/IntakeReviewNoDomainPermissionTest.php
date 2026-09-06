<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review access is not coupled to a business-domain permission. */
final class IntakeReviewNoDomainPermissionTest extends UnitTestCase {

  public function testReviewRouteUsesIntakePermissionOnly(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    $review = substr($routing, (int) strpos($routing, 'brebo_data_intake.review:'));
    self::assertStringContainsString("review brebo office intake", $review);
    self::assertStringNotContainsString('finance', strtolower($review));
    self::assertStringNotContainsString('project', strtolower($review));
  }

}
