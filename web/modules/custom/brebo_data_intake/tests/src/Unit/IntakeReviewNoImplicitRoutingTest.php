<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies viewing pending work cannot route it into a business module. */
final class IntakeReviewNoImplicitRoutingTest extends UnitTestCase {

  public function testControllerDoesNotInvokeDestinationRouting(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('destination', strtolower($source));
    self::assertStringNotContainsString('dispatch', strtolower($source));
  }

}
