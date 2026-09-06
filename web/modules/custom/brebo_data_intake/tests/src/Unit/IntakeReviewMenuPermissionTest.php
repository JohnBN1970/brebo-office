<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the menu points to the permission-protected route. */
final class IntakeReviewMenuPermissionTest extends UnitTestCase {

  public function testMenuUsesProtectedReviewRoute(): void {
    $root = dirname(__DIR__, 3);
    $menu = file_get_contents($root . '/brebo_data_intake.links.menu.yml');
    $routing = file_get_contents($root . '/brebo_data_intake.routing.yml');
    self::assertIsString($menu);
    self::assertIsString($routing);
    self::assertStringContainsString('route_name: brebo_data_intake.review', $menu);
    self::assertStringContainsString("_permission: 'review brebo office intake'", $routing);
  }

}
