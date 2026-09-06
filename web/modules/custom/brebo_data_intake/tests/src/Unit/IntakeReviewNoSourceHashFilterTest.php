<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies pending work is not filtered by source hash availability. */
final class IntakeReviewNoSourceHashFilterTest extends UnitTestCase {

  public function testRepositoryDoesNotConditionOnSourceHash(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("condition('run.source_hash'", $source);
  }

}
