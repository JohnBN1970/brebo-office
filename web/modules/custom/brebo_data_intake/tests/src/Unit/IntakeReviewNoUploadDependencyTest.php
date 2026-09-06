<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review workbench does not depend on the manual upload form. */
final class IntakeReviewNoUploadDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceUploadForm(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('SourceNeutralUploadForm', $source);
  }

}
