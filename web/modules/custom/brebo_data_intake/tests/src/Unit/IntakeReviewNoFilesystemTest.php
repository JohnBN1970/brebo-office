<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review read model does not depend on filesystem layout. */
final class IntakeReviewNoFilesystemTest extends UnitTestCase {

  public function testReviewClassesDoNotUseFilesystemService(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('file_system', $source);
    self::assertStringNotContainsString('FileSystemInterface', $source);
  }

}
