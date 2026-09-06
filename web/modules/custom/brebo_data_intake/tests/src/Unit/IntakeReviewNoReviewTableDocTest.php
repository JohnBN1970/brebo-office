<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies documentation does not introduce a separate review store. */
final class IntakeReviewNoReviewTableDocTest extends UnitTestCase {

  public function testReadmeDescribesReadModelOnly(): void {
    $readme = file_get_contents(dirname(__DIR__, 3) . '/README.md');
    self::assertIsString($readme);
    self::assertStringContainsString('alleen het leesmodel', $readme);
  }

}
