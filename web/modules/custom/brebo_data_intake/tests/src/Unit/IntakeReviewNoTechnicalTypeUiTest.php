<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies source type remains available to the model but hidden from the first queue. */
final class IntakeReviewNoTechnicalTypeUiTest extends UnitTestCase {

  public function testControllerDoesNotExposeSourceType(): void {
    $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($controller);
    self::assertStringNotContainsString('source_type', $controller);
  }

}
