<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies canonical record identity remains available for detail actions. */
final class IntakeReviewRecordIdTest extends UnitTestCase {

  public function testRepositorySelectsRecordId(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("['id', 'record_type'", $source);
  }

}
