<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies pending intake is not hidden behind a stale render cache. */
final class IntakeReviewCacheTest extends UnitTestCase {

  public function testWorkbenchDisablesRenderCache(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("'#cache' => ['max-age' => 0]", $source);
  }

}
