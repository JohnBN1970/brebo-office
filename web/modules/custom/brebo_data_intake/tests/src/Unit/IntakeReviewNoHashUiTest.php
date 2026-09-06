<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies deduplication hashes stay under the hood. */
final class IntakeReviewNoHashUiTest extends UnitTestCase {

  public function testReviewClassesDoNotExposeHashes(): void {
    $root = dirname(__DIR__, 3);
    $controller = file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($controller);
    self::assertStringNotContainsString('content_hash', $controller);
    self::assertStringNotContainsString('source_hash', $controller);
  }

}
