<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies provenance fields are selected for review. */
final class IntakeReviewProvenanceColumnsTest extends UnitTestCase {

  public function testRepositorySelectsSourceReferenceAndProvider(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("'source_reference'", $source);
    self::assertStringContainsString("addField('source', 'provider_key', 'provider_key')", $source);
    self::assertStringContainsString("addField('source', 'source_type', 'source_type')", $source);
  }

}
