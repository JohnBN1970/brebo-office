<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the queue does not filter to one provider. */
final class IntakeReviewNoProviderFilterTest extends UnitTestCase {

  public function testRepositoryDoesNotConditionOnProvider(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("condition('source.provider_key'", $source);
  }

}
