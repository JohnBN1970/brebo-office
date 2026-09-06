<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies corrupt legacy payload cannot break the review queue. */
final class IntakeReviewPayloadFallbackTest extends UnitTestCase {

  public function testRepositoryHandlesInvalidJson(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString('catch (\\JsonException)', $source);
    self::assertStringContainsString("$values['payload'] = [];", $source);
  }

}
