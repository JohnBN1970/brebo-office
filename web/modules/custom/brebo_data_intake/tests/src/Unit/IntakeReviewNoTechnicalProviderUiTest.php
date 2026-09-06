<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies provider keys remain available to the model but hidden from the operator queue. */
final class IntakeReviewNoTechnicalProviderUiTest extends UnitTestCase {

  public function testControllerUsesHumanSourceLabelOnly(): void {
    $controller = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($controller);
    self::assertStringContainsString("record['source_label']", $controller);
    self::assertStringNotContainsString('provider_key', $controller);
  }

}
