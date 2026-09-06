<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies source registry deletion remains impossible from the workbench. */
final class IntakeReviewNoSourceDeletionTest extends UnitTestCase {

  public function testReviewRepositoryHasNoDeleteQuery(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('->delete(', $source);
  }

}
