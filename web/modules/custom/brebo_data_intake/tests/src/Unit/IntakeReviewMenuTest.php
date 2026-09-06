<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the intake workbench is discoverable in Office navigation. */
final class IntakeReviewMenuTest extends UnitTestCase {

  public function testMainMenuLinksToReviewWorkbench(): void {
    $menu = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.links.menu.yml');
    self::assertIsString($menu);
    self::assertStringContainsString("title: 'Intake'", $menu);
    self::assertStringContainsString('route_name: brebo_data_intake.review', $menu);
    self::assertStringContainsString('menu_name: main', $menu);
  }

}
