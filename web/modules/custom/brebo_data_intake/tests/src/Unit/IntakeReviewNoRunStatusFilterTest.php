<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies record review status, not run status, owns the pending queue. */
final class IntakeReviewNoRunStatusFilterTest extends UnitTestCase {

  public function testRepositoryDoesNotConditionOnRunStatus(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("condition('run.status'", $source);
  }

}
