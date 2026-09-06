<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review permission ships through a new update hook. */
final class IntakeReviewUpdateHookTest extends UnitTestCase {

  public function testReviewPermissionUsesNewUpdateHook(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    self::assertStringContainsString('function brebo_data_intake_update_11002(): string', $install);
  }

}
