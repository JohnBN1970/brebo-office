<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies viewing pending intake cannot silently accept a masterdata candidate. */
final class IntakeReviewNoCandidateMutationTest extends UnitTestCase {

  public function testRepositoryDoesNotWriteCandidates(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('brebo_masterdata_candidate', $source);
  }

}
