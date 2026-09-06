<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies source provenance is not rewritten by presentation code. */
final class IntakeReviewNoSourceReferenceMutationTest extends UnitTestCase {

  public function testReviewClassesDoNotAssignSourceReference(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString("source_reference' =>", $source);
  }

}
