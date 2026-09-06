<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the queue does not filter to mail, upload, or another source type. */
final class IntakeReviewNoSourceTypeFilterTest extends UnitTestCase {

  public function testRepositoryDoesNotConditionOnSourceType(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("condition('source.source_type'", $source);
  }

}
