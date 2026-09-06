<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies internal source references are retained but not dumped in the queue. */
final class IntakeReviewNoRawSourceReferenceTest extends UnitTestCase {

  public function testControllerDoesNotRenderRawSourceReference(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("record['source_reference']", $source);
  }

}
