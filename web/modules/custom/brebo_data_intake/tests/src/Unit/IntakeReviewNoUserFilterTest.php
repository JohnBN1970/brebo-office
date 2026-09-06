<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first queue is shared operational work, not a personal inbox. */
final class IntakeReviewNoUserFilterTest extends UnitTestCase {

  public function testRepositoryDoesNotFilterByCurrentUser(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('current_user', $source);
    self::assertStringNotContainsString('uid', $source);
  }

}
