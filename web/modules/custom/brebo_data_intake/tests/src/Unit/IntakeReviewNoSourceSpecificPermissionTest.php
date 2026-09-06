<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review access is not split into source-specific permissions. */
final class IntakeReviewNoSourceSpecificPermissionTest extends UnitTestCase {

  public function testPermissionsHaveNoMailOrUploadReviewVariant(): void {
    $permissions = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.permissions.yml');
    self::assertIsString($permissions);
    self::assertStringNotContainsString('review mail intake', $permissions);
    self::assertStringNotContainsString('review upload intake', $permissions);
  }

}
