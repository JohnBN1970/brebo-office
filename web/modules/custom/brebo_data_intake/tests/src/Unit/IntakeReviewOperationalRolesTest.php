<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review permission is not broadened beyond current operational roles. */
final class IntakeReviewOperationalRolesTest extends UnitTestCase {

  public function testGrantHelperNamesOnlyCurrentOperationalRoles(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    self::assertStringContainsString("foreach (['brebo_projectleider', 'brebo_werkvoorbereider'] as $role_id)", $install);
  }

}
