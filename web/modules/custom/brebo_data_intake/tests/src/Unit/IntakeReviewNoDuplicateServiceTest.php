<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies central intake has one review read-model service. */
final class IntakeReviewNoDuplicateServiceTest extends UnitTestCase {

  public function testReviewRepositoryServiceDeclaredOnce(): void {
    $services = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.services.yml');
    self::assertIsString($services);
    self::assertSame(1, substr_count($services, 'brebo_data_intake.review_repository:'));
  }

}
