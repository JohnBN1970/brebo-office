<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench query is bounded for production use. */
final class IntakeReviewLimitTest extends UnitTestCase {

  public function testQueueHasHardUpperBound(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString('min(200, $limit)', $source);
    self::assertStringContainsString("orderBy('record.created', 'ASC')", $source);
  }

}
