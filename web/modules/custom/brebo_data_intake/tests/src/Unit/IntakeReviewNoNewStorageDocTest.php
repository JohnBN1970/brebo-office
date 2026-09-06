<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies canonical-source ownership remains explicit. */
final class IntakeReviewNoNewStorageDocTest extends UnitTestCase {

  public function testReadmeSaysOriginalSourceRemainsCanonical(): void {
    $readme = file_get_contents(dirname(__DIR__, 3) . '/README.md');
    self::assertIsString($readme);
    self::assertStringContainsString('originele bronbestand of bronbericht blijft canoniek', $readme);
  }

}
