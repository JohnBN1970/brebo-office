<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first workbench does not introduce an unnecessary API route. */
final class IntakeReviewNoApiRouteTest extends UnitTestCase {

  public function testRoutingHasNoReviewApiEndpoint(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertStringNotContainsString('/api/', $routing);
    self::assertStringNotContainsString('_format:', $routing);
  }

}
