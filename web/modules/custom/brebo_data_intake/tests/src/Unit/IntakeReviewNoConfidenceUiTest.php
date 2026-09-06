<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies raw confidence stays available without cluttering the first queue. */
final class IntakeReviewNoConfidenceUiTest extends UnitTestCase {

  public function testControllerDoesNotRenderRawConfidence(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("record['confidence']", $source);
  }

}
