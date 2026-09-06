<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies generic source type remains available to later UI treatment. */
final class IntakeReviewSourceTypeTest extends UnitTestCase {

  public function testRepositorySelectsGenericSourceType(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString("addField('source', 'source_type', 'source_type')", $source);
  }

}
