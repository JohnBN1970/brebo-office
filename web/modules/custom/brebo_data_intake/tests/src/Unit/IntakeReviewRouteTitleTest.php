<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review route has a stable human-facing title. */
final class IntakeReviewRouteTitleTest extends UnitTestCase {

  public function testReviewRouteTitle(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertStringContainsString("_title: 'Intakewerkbank'", $routing);
  }

}
