<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies confidence remains available to later review UI slices. */
final class IntakeReviewConfidenceTest extends UnitTestCase {

  public function testRepositorySelectsConfidence(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("'confidence'", $source);
  }

}
