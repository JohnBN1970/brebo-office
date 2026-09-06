<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the read-only slice does not pretend a review decision happened. */
final class IntakeReviewNoReviewAuditYetTest extends UnitTestCase {

  public function testReviewClassesDoNotWriteReviewerFields(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('reviewed_by', $source);
    self::assertStringNotContainsString('reviewed_at', $source);
  }

}
