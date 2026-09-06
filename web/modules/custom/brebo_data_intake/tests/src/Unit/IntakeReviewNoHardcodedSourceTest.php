<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies source labels come from the source registry. */
final class IntakeReviewNoHardcodedSourceTest extends UnitTestCase {

  public function testControllerDoesNotHardcodeSourceNames(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("'Mail'", $source);
    self::assertStringNotContainsString("'Upload'", $source);
    self::assertStringContainsString("record['source_label']", $source);
  }

}
