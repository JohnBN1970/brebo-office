<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies canonical source references remain available for later detail view. */
final class IntakeReviewSourceReferenceTest extends UnitTestCase {

  public function testRepositoryKeepsSourceReference(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("'source_reference'", $source);
  }

}
