<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies decision writes are explicitly reserved for destination contracts. */
final class IntakeReviewDestinationBoundaryTest extends UnitTestCase {

  public function testDocumentationNamesDestinationContractBoundary(): void {
    $readme = file_get_contents(dirname(__DIR__, 3) . '/README.md');
    self::assertIsString($readme);
    self::assertStringContainsString('destination-contracten', $readme);
    self::assertStringContainsString('niet als directe database- of vakmodulewrite vanuit de UI', $readme);
  }

}
