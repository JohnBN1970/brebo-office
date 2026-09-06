<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review overview only reads normalized payload. */
final class IntakeReviewNoPayloadMutationTest extends UnitTestCase {

  public function testControllerDoesNotModifyPayload(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("$payload['classification'] =", $source);
    self::assertStringNotContainsString("$payload['project_label'] =", $source);
  }

}
