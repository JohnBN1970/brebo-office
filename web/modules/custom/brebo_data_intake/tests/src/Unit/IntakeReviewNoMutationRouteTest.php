<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first workbench slice exposes no mutation endpoint. */
final class IntakeReviewNoMutationRouteTest extends UnitTestCase {

  public function testNoAcceptRejectRoutesYet(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertStringNotContainsString('/accept', $routing);
    self::assertStringNotContainsString('/reject', $routing);
    self::assertStringNotContainsString('/correct', $routing);
  }

}
