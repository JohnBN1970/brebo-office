<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies classification labels tolerate source-neutral envelopes. */
final class IntakeReviewClassificationFallbackTest extends UnitTestCase {

  public function testControllerReadsEnvelopeClassification(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$envelope", $source);
    self::assertStringContainsString("'classification'", $source);
  }

}
