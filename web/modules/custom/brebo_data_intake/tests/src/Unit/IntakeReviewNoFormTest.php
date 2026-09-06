<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first workbench slice is an overview, not an unsafe mutation form. */
final class IntakeReviewNoFormTest extends UnitTestCase {

  public function testReviewRouteUsesControllerNotForm(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    $review = substr($routing, (int) strpos($routing, 'brebo_data_intake.review:'));
    self::assertStringContainsString('_controller:', $review);
    self::assertStringNotContainsString('_form:', $review);
  }

}
