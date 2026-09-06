<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies queue contents come from canonical intake storage, not tempstore. */
final class IntakeReviewNoTempStoreTest extends UnitTestCase {

  public function testReviewClassesDoNotUseTempStore(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('tempstore', strtolower($source));
  }

}
