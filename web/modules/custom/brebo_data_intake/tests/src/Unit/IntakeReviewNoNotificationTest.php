<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies simply viewing pending work cannot send notifications. */
final class IntakeReviewNoNotificationTest extends UnitTestCase {

  public function testReviewClassesDoNotSendMailOrNotifications(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('mailManager', $source);
    self::assertStringNotContainsString('notification', strtolower($source));
  }

}
