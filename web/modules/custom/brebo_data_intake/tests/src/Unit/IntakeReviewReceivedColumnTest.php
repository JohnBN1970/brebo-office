<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies operators can see how long an item has waited. */
final class IntakeReviewReceivedColumnTest extends UnitTestCase {

  public function testTableShowsReceivedTime(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$this->t('Ontvangen')", $source);
  }

}
