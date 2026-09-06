<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first workbench does not require client-side rendering. */
final class IntakeReviewNoJavascriptTest extends UnitTestCase {

  public function testControllerHasNoJavascriptDependency(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('#attached', $source);
    self::assertStringNotContainsString('drupalSettings', $source);
  }

}
