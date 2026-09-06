<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the queue presents the source as a human label. */
final class IntakeReviewNoSourceTypeUiTest extends UnitTestCase {

  public function testControllerDoesNotRenderRawSourceType(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("record['source_type']", $source);
  }

}
