<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies missing machine guesses remain understandable to operators. */
final class IntakeReviewHumanFallbackTest extends UnitTestCase {

  public function testControllerUsesHumanFallbacks(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('Nog te bepalen', $source);
    self::assertStringContainsString('Nog te koppelen', $source);
    self::assertStringContainsString('Zonder omschrijving', $source);
  }

}
