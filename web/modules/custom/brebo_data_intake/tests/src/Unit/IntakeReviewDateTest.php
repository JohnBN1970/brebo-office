<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies received timestamps use Drupal's local date formatting. */
final class IntakeReviewDateTest extends UnitTestCase {

  public function testControllerUsesDateFormatter(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('DateFormatterInterface', $source);
    self::assertStringContainsString("->format((int) $record['created'], 'short')", $source);
  }

}
