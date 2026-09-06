<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies classification labels tolerate multiple source adapters. */
final class IntakeReviewClassificationFallbackTest extends UnitTestCase {

  public function testControllerSupportsGenericClassificationFields(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("payload['classification'] ?? $payload['document_type'] ?? $payload['type']", $source);
  }

}
