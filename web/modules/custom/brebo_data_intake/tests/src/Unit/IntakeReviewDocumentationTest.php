<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Keeps the review decision boundary explicit while the UI evolves. */
final class IntakeReviewDocumentationTest extends UnitTestCase {

  public function testReadModelBoundaryIsDocumented(): void {
    $readme = file_get_contents(dirname(__DIR__, 3) . '/README.md');
    self::assertIsString($readme);
    self::assertStringContainsString('alleen het leesmodel', $readme);
    self::assertStringContainsString('centrale intake/destination-contracten', $readme);
    self::assertStringContainsString('originele bronbestand of bronbericht blijft canoniek', $readme);
  }

}
