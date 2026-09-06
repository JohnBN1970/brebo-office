<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies database concerns stay behind the review repository. */
final class IntakeReviewNoDirectSqlTest extends UnitTestCase {

  public function testControllerHasNoDatabaseDependency(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('Connection', $source);
    self::assertStringNotContainsString("get('database')", $source);
  }

}
