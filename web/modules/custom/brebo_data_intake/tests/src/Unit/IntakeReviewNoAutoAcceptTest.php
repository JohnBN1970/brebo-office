<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies rendering the queue cannot auto-accept pending records. */
final class IntakeReviewNoAutoAcceptTest extends UnitTestCase {

  public function testReviewClassesDoNotSetAcceptedStatus(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString("status' => 'accepted", $source);
    self::assertStringNotContainsString('markAccepted', $source);
  }

}
