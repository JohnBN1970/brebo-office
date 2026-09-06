<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies rendering the queue does not emit domain events. */
final class IntakeReviewNoEventDispatchTest extends UnitTestCase {

  public function testReviewClassesDoNotDispatchEvents(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('event_dispatcher', $source);
    self::assertStringNotContainsString('dispatch(', $source);
  }

}
