<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the overview path remains strictly read-only at render time. */
final class IntakeReviewNoRenderTimeWriteTest extends UnitTestCase {

  public function testOverviewOnlyCallsPendingReadModel(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('$this->reviews->pending()', $source);
    self::assertStringNotContainsString('->save(', $source);
    self::assertStringNotContainsString('->delete(', $source);
  }

}
