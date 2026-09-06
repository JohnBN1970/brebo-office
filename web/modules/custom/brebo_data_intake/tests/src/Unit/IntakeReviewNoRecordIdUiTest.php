<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies internal record IDs do not become operator-facing labels. */
final class IntakeReviewNoRecordIdUiTest extends UnitTestCase {

  public function testControllerDoesNotRenderRecordId(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("record['id']", $source);
  }

}
