<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review_required is translated into an operator action. */
final class IntakeReviewNoTechnicalStatusUiTest extends UnitTestCase {

  public function testControllerDoesNotRenderTechnicalStatus(): void {
    $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($controller);
    self::assertStringNotContainsString('review_required', $controller);
    self::assertStringContainsString('Controleren', $controller);
  }

}
