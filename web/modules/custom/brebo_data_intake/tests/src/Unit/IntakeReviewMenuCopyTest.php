<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies navigation describes the operator task. */
final class IntakeReviewMenuCopyTest extends UnitTestCase {

  public function testMenuDescriptionIsActionOriented(): void {
    $menu = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.links.menu.yml');
    self::assertIsString($menu);
    self::assertStringContainsString('Controleer binnengekomen items die aandacht nodig hebben.', $menu);
  }

}
