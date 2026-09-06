<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies project relation is sourced from normalized data. */
final class IntakeReviewNoHardcodedProjectTest extends UnitTestCase {

  public function testControllerUsesPayloadProjectLabel(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("payload['project_label']", $source);
  }

}
