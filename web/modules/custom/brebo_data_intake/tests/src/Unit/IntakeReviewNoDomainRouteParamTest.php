<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the landing queue is not scoped to a project or finance domain. */
final class IntakeReviewNoDomainRouteParamTest extends UnitTestCase {

  public function testReviewRouteHasNoDomainParameter(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertStringNotContainsString('{project}', $routing);
    self::assertStringNotContainsString('{invoice}', $routing);
  }

}
