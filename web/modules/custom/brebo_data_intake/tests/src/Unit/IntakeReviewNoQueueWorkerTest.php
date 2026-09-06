<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies listing pending intake does not require a queue worker. */
final class IntakeReviewNoQueueWorkerTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceQueueWorkers(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('QueueWorker', $source);
  }

}
