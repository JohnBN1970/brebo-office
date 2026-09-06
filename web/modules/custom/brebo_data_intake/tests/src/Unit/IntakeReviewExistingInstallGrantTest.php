<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies existing installations receive the new review permission. */
final class IntakeReviewExistingInstallGrantTest extends UnitTestCase {

  public function testUpdate11002UsesOperationalPermissionHelper(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    $update = substr($install, (int) strpos($install, 'function brebo_data_intake_update_11002'));
    self::assertStringContainsString('_brebo_data_intake_grant_operational_permissions()', $update);
  }

}
