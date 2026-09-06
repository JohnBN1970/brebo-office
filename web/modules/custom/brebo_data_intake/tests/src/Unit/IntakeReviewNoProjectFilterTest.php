<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the queue includes unlinked records instead of requiring a project first. */
final class IntakeReviewNoProjectFilterTest extends UnitTestCase {

  public function testRepositoryDoesNotConditionOnProject(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('project_id', $source);
    self::assertStringNotContainsString('project_label', $source);
  }

}
