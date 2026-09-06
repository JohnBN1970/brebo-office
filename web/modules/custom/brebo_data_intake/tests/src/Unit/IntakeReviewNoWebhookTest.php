<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies viewing pending work cannot trigger integrations. */
final class IntakeReviewNoWebhookTest extends UnitTestCase {

  public function testReviewClassesDoNotCallWebhooks(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('webhook', strtolower($source));
  }

}
