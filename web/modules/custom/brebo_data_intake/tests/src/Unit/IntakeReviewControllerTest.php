<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench keeps operator-facing language explicit. */
final class IntakeReviewControllerTest extends UnitTestCase {

  public function testControllerContainsOperatorQuestion(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('Wat moet ik doen?', $source);
    self::assertStringContainsString('Office denkt', $source);
    self::assertStringContainsString('Hoort bij', $source);
  }

}
