<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench renders a human source label, not an internal ID. */
final class IntakeReviewSourceLabelTest extends UnitTestCase {

  public function testControllerRendersSourceLabel(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("record['source_label']", $source);
    self::assertStringNotContainsString("record['source_id']", $source);
  }

}
