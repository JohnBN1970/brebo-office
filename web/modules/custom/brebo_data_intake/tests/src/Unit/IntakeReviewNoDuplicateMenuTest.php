<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies central intake has one main-menu entry. */
final class IntakeReviewNoDuplicateMenuTest extends UnitTestCase {

  public function testMenuDeclaresIntakeOnce(): void {
    $menu = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.links.menu.yml');
    self::assertIsString($menu);
    self::assertSame(1, substr_count($menu, "title: 'Intake'"));
  }

}
