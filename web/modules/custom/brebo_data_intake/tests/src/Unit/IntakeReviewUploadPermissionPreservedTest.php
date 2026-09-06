<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies manual upload retains its existing submission permission. */
final class IntakeReviewUploadPermissionPreservedTest extends UnitTestCase {

  public function testUploadRouteKeepsSubmitPermission(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    $upload = substr($routing, 0, (int) strpos($routing, 'brebo_data_intake.review:'));
    self::assertStringContainsString("_permission: 'submit brebo office intake'", $upload);
  }

}
