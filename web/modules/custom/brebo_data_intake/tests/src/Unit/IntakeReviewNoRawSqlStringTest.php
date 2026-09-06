<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review repository uses Drupal's database API. */
final class IntakeReviewNoRawSqlStringTest extends UnitTestCase {

  public function testRepositoryHasNoRawSelectSql(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('SELECT ', $source);
    self::assertStringContainsString("$this->database->select", $source);
  }

}
