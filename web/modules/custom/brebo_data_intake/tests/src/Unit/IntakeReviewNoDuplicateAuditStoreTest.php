<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench does not invent a second review audit table. */
final class IntakeReviewNoDuplicateAuditStoreTest extends UnitTestCase {

  public function testInstallHasNoWorkbenchSpecificSchema(): void {
    $install = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.install');
    self::assertIsString($install);
    self::assertStringNotContainsString('workbench_review', $install);
    self::assertStringNotContainsString('intake_decision', $install);
  }

}
