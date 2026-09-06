<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review slice requires no database schema migration. */
final class IntakeReviewNoSchemaUpdateTest extends UnitTestCase {

  public function testUpdate11002OnlyGrantsPermissions(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    $update = substr($install, (int) strpos($install, 'function brebo_data_intake_update_11002'));
    self::assertStringNotContainsString('getSchema()', $update);
    self::assertStringNotContainsString('addField(', $update);
    self::assertStringNotContainsString('createTable(', $update);
  }

}
