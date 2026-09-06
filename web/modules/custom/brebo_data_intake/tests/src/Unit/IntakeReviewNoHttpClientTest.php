<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies rendering pending work never depends on an external API. */
final class IntakeReviewNoHttpClientTest extends UnitTestCase {

  public function testReviewClassesDoNotUseHttpClient(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('http_client', $source);
    self::assertStringNotContainsString('ClientInterface', $source);
  }

}
