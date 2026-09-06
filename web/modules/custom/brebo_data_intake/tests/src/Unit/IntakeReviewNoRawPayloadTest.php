<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the operator table does not dump raw normalized payload. */
final class IntakeReviewNoRawPayloadTest extends UnitTestCase {

  public function testControllerUsesSelectedHumanFields(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("json_encode($payload", $source);
    self::assertStringNotContainsString("print_r($payload", $source);
  }

}
