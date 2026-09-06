<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench reuses the existing record status index. */
final class IntakeReviewNoSchemaDuplicationTest extends UnitTestCase {

  public function testCanonicalRecordAlreadyIndexesStatus(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    self::assertStringContainsString("'status' => ['status']", $install);
  }

}
