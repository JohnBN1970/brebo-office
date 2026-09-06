<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench uses one clear review permission. */
final class IntakeReviewNoDuplicatePermissionTest extends UnitTestCase {

  public function testReviewPermissionDeclaredOnce(): void {
    $permissions = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.permissions.yml');
    self::assertIsString($permissions);
    self::assertSame(1, substr_count($permissions, "review brebo office intake:\n"));
  }

}
