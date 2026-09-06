<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies loading the workbench never changes record status. */
final class IntakeReviewNoImplicitAcceptanceTest extends UnitTestCase {

  public function testOverviewMethodContainsNoStatusWrites(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString("'accepted'", $source);
    self::assertStringNotContainsString("'rejected'", $source);
  }

}
