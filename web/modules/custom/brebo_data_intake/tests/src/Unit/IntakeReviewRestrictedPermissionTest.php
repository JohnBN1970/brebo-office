<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review access is explicitly marked restricted. */
final class IntakeReviewRestrictedPermissionTest extends UnitTestCase {

  public function testReviewPermissionIsRestricted(): void {
    $permissions = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.permissions.yml');
    self::assertIsString($permissions);
    $review = substr($permissions, (int) strpos($permissions, 'review brebo office intake:'));
    self::assertStringContainsString('restrict access: true', $review);
  }

}
