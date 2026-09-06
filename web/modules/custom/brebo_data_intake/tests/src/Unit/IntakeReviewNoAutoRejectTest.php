<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies rendering the queue cannot auto-reject pending records. */
final class IntakeReviewNoAutoRejectTest extends UnitTestCase {

  public function testReviewClassesDoNotSetRejectedStatus(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString("status' => 'rejected", $source);
    self::assertStringNotContainsString('markRejected', $source);
  }

}
