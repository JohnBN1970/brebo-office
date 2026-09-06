<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies provider internals stay behind the human source label. */
final class IntakeReviewNoProviderUiTest extends UnitTestCase {

  public function testControllerDoesNotRenderProviderKey(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("record['provider_key']", $source);
  }

}
