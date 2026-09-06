<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies source-neutral intent stays explicit. */
final class IntakeReviewSourceNeutralDocTest extends UnitTestCase {

  public function testReadmeNamesMultipleSourceAdapters(): void {
    $readme = file_get_contents(dirname(__DIR__, 3) . '/README.md');
    self::assertIsString($readme);
    self::assertStringContainsString('Mail, upload en toekomstige adapters', $readme);
  }

}
