<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies pending rows communicate the immediate operator action. */
final class IntakeReviewStatusCopyTest extends UnitTestCase {

  public function testPendingRowsSayControleren(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$this->t('Controleren')", $source);
  }

}
