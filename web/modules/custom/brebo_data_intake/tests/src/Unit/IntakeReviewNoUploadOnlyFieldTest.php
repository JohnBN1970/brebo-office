<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the queue tolerates records without upload-only metadata. */
final class IntakeReviewNoUploadOnlyFieldTest extends UnitTestCase {

  public function testFilenameIsOnlyOneDescriptionFallback(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("payload['subject'] ?? $payload['filename']", $source);
    self::assertStringNotContainsString("$payload['fid']", $source);
  }

}
