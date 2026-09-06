<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies operators see what actually arrived. */
final class IntakeReviewSubjectColumnTest extends UnitTestCase {

  public function testTableShowsIncomingItemColumn(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('Wat is binnengekomen', $source);
  }

}
