<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies viewing the queue cannot consume or alter a pending item. */
final class IntakeReviewNoStatusMutationTest extends UnitTestCase {

  public function testRepositoryOnlySelectsRecords(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("select('brebo_data_record'", $source);
    self::assertStringNotContainsString("update('brebo_data_record'", $source);
  }

}
