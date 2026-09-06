<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review workbench has its own restricted permission. */
final class IntakeReviewRoutingTest extends UnitTestCase {

  public function testReviewRouteUsesDedicatedPermission(): void {
    $root = dirname(__DIR__, 3);
    $routing = file_get_contents($root . '/brebo_data_intake.routing.yml');
    $permissions = file_get_contents($root . '/brebo_data_intake.permissions.yml');
    self::assertIsString($routing);
    self::assertIsString($permissions);
    self::assertStringContainsString("path: '/brebo-office/intake'", $routing);
    self::assertStringContainsString("_permission: 'review brebo office intake'", $routing);
    self::assertStringContainsString('review brebo office intake:', $permissions);
    self::assertStringContainsString('restrict access: true', $permissions);
  }

}
