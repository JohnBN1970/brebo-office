<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies viewing pending intake cannot alter classification masterdata. */
final class IntakeReviewNoClassificationMutationTest extends UnitTestCase {

  public function testRepositoryDoesNotWriteClassificationTerms(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('brebo_classification_term', $source);
  }

}
