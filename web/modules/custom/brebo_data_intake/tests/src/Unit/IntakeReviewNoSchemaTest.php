<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review workbench reuses the canonical intake tables. */
final class IntakeReviewNoSchemaTest extends UnitTestCase {

  public function testNoReviewTableWasAdded(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    self::assertStringNotContainsString("schema['brebo_data_review']", $install);
    self::assertStringNotContainsString("schema['brebo_intake_review']", $install);
  }

}
