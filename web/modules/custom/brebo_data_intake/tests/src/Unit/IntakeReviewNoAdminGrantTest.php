<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the module does not broaden review access to generic roles. */
final class IntakeReviewNoAdminGrantTest extends UnitTestCase {

  public function testGrantHelperDoesNotNameGenericRoles(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    self::assertStringNotContainsString("'administrator'", $install);
    self::assertStringNotContainsString("'authenticated'", $install);
    self::assertStringNotContainsString("'anonymous'", $install);
  }

}
