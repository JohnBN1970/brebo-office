<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies existing operational roles receive review access on update. */
final class IntakeReviewPermissionUpdateTest extends UnitTestCase {

  public function testUpdateHookGrantsReviewPermission(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    self::assertStringContainsString('brebo_data_intake_update_11002', $install);
    self::assertStringContainsString("'review brebo office intake'", $install);
    self::assertStringContainsString("'brebo_projectleider', 'brebo_werkvoorbereider'", $install);
  }

}
