<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies external IDs are only a final fallback for description. */
final class IntakeReviewNoExternalKeyUiTest extends UnitTestCase {

  public function testExternalKeyIsOnlySubjectFallback(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertSame(1, substr_count($source, "record['external_key']"));
  }

}
