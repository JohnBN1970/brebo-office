<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review-required records are not hidden by confidence thresholds. */
final class IntakeReviewNoConfidenceFilterTest extends UnitTestCase {

  public function testRepositoryDoesNotFilterByConfidence(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("condition('record.confidence'", $source);
  }

}
