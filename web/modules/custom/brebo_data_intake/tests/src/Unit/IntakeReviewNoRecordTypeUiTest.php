<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies normalized technical record type does not replace human classification. */
final class IntakeReviewNoRecordTypeUiTest extends UnitTestCase {

  public function testControllerDoesNotRenderRawRecordType(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("record['record_type']", $source);
  }

}
