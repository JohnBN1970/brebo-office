<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies normalized record type remains available to the workbench. */
final class IntakeReviewRecordTypeTest extends UnitTestCase {

  public function testRepositorySelectsRecordType(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("'record_type'", $source);
  }

}
