<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies queue order stays deterministic. */
final class IntakeReviewOldestFirstReasonTest extends UnitTestCase {

  public function testQueueOrderingIsDeterministicByCreated(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("orderBy('record.created', 'ASC')", $source);
  }

}
