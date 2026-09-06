<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies provider provenance remains available to later detail views. */
final class IntakeReviewProviderTest extends UnitTestCase {

  public function testRepositorySelectsProviderKey(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("addField('source', 'provider_key', 'provider_key')", $source);
  }

}
