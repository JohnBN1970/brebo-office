<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review repository remains a database-backed read service. */
final class IntakeReviewServiceTest extends UnitTestCase {

  public function testReviewRepositoryServiceIsWired(): void {
    $services = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.services.yml');
    self::assertIsString($services);
    self::assertStringContainsString('brebo_data_intake.review_repository:', $services);
    self::assertStringContainsString('Drupal\\brebo_data_intake\\Service\\IntakeReviewRepository', $services);
    self::assertStringContainsString("arguments: ['@database']", $services);
  }

}
