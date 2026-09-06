<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the read-only workbench does not open write transactions. */
final class IntakeReviewNoTransactionTest extends UnitTestCase {

  public function testRepositoryDoesNotStartTransaction(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('startTransaction', $source);
  }

}
