<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies routing targets one canonical review controller. */
final class IntakeReviewNoDuplicateControllerTest extends UnitTestCase {

  public function testRoutingReferencesReviewControllerOnce(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertSame(1, substr_count($routing, 'IntakeReviewController::overview'));
  }

}
