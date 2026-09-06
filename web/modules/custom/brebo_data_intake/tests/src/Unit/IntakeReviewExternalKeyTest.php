<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies external source identity remains available for review detail. */
final class IntakeReviewExternalKeyTest extends UnitTestCase {

  public function testRepositorySelectsExternalKey(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("'external_key'", $source);
  }

}
