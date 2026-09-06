<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies ingest-run internals stay under the hood. */
final class IntakeReviewNoRunIdUiTest extends UnitTestCase {

  public function testControllerDoesNotExposeRunId(): void {
    $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($controller);
    self::assertStringNotContainsString('run_id', $controller);
  }

}
