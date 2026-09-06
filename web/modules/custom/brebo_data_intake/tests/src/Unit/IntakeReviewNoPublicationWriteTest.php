<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies review presentation cannot publish content. */
final class IntakeReviewNoPublicationWriteTest extends UnitTestCase {

  public function testReviewClassesDoNotReferencePublicationActions(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('publish', strtolower($source));
  }

}
