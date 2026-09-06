<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench uses the existing review-required state. */
final class IntakeReviewNoNewStatusTest extends UnitTestCase {

  public function testRepositoryUsesExistingReviewRequiredState(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("'review_required'", $source);
    self::assertStringNotContainsString("'pending_review'", $source);
  }

}
