<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the overview presents a classification suggestion without persisting it. */
final class IntakeReviewNoAutomaticClassificationTest extends UnitTestCase {

  public function testControllerOnlyDisplaysClassificationSuggestion(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("payload['classification']", $source);
    self::assertStringNotContainsString('setClassification', $source);
    self::assertStringNotContainsString('saveClassification', $source);
  }

}
