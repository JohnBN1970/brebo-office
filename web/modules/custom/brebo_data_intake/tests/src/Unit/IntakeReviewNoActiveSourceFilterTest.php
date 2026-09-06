<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies pending records remain reviewable even if their source is later disabled. */
final class IntakeReviewNoActiveSourceFilterTest extends UnitTestCase {

  public function testRepositoryDoesNotHideInactiveSourceHistory(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("condition('source.active'", $source);
  }

}
