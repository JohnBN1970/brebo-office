<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies known human labels take precedence over machine identifiers. */
final class IntakeReviewPayloadPriorityTest extends UnitTestCase {

  public function testControllerPrefersHumanPayloadFields(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("payload['project_label'] ?? $payload['project_name']", $source);
    self::assertStringContainsString("payload['subject'] ?? $payload['filename'] ?? $payload['original_filename']", $source);
  }

}
