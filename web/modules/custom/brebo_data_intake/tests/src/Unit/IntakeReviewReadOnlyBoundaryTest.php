<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Guards the first workbench slice against direct domain writes. */
final class IntakeReviewReadOnlyBoundaryTest extends UnitTestCase {

  public function testReviewRepositoryIsReadOnly(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('->insert(', $source);
    self::assertStringNotContainsString('->update(', $source);
    self::assertStringNotContainsString('->delete(', $source);
    self::assertStringContainsString("condition('record.status', 'review_required')", $source);
  }

}
