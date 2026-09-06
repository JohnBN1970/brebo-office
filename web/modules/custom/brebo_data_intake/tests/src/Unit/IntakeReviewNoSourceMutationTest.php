<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies opening the workbench cannot alter registered sources. */
final class IntakeReviewNoSourceMutationTest extends UnitTestCase {

  public function testRepositoryDoesNotUpdateSources(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("update('brebo_data_source'", $source);
    self::assertStringNotContainsString("delete('brebo_data_source'", $source);
  }

}
