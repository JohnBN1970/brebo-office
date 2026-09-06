<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies rendering pending work does not create side-effect messages. */
final class IntakeReviewNoMessengerSideEffectTest extends UnitTestCase {

  public function testControllerDoesNotUseMessenger(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('messenger()', $source);
  }

}
