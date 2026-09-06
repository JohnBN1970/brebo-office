<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies opening the workbench cannot mutate ingest-run audit history. */
final class IntakeReviewNoRunMutationTest extends UnitTestCase {

  public function testRepositoryDoesNotUpdateIngestRun(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("update('brebo_data_ingest_run'", $source);
    self::assertStringNotContainsString("delete('brebo_data_ingest_run'", $source);
  }

}
