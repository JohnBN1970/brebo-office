<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first review overview needs no batch process. */
final class IntakeReviewNoBatchTest extends UnitTestCase {

  public function testReviewClassesDoNotUseBatchApi(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('batch_set', $source);
  }

}
