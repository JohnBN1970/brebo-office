<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review-required is the queue source of truth. */
final class IntakeReviewCanonicalStatusTest extends UnitTestCase {

  public function testReadmeNamesReviewRequiredStatus(): void {
    $readme = file_get_contents(dirname(__DIR__, 3) . '/README.md');
    self::assertIsString($readme);
    self::assertStringContainsString('`review_required`', $readme);
  }

}
