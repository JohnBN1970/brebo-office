<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the queue displays adapter-provided classification, not a fixed domain type. */
final class IntakeReviewNoHardcodedClassificationTest extends UnitTestCase {

  public function testControllerDoesNotHardcodeInvoiceClassification(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("'Factuur'", $source);
    self::assertStringNotContainsString("'Offerte'", $source);
  }

}
