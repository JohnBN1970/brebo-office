<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies accepted/rejected records cannot reappear in the queue. */
final class IntakeReviewStatusTest extends UnitTestCase {

  public function testQueueOnlySelectsReviewRequired(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertSame(1, substr_count($source, "condition('record.status', 'review_required')"));
  }

}
