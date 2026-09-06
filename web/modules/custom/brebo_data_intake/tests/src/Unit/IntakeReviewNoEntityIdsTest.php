<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first workbench remains human-readable. */
final class IntakeReviewNoEntityIdsTest extends UnitTestCase {

  public function testControllerDoesNotRenderProjectIds(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("payload['project_id']", $source);
    self::assertStringNotContainsString("payload['building_id']", $source);
  }

}
