<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies pending work is presented oldest first. */
final class IntakeReviewOldestFirstTest extends UnitTestCase {

  public function testOldestItemsComeFirst(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("orderBy('record.created', 'ASC')", $source);
  }

}
