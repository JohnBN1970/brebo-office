<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the operator UI does not expose internal source identifiers. */
final class IntakeReviewNoSourceIdTest extends UnitTestCase {

  public function testControllerAvoidsSourceIds(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('source_id', $source);
  }

}
