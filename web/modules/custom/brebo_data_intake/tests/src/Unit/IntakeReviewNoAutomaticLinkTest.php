<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review overview cannot silently persist a suggested relation. */
final class IntakeReviewNoAutomaticLinkTest extends UnitTestCase {

  public function testControllerOnlyDisplaysProjectSuggestion(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("payload['project_label']", $source);
    self::assertStringNotContainsString('setProject', $source);
    self::assertStringNotContainsString('linkProject', $source);
  }

}
