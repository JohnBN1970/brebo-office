<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the overview cannot silently choose a destination module. */
final class IntakeReviewNoAutomaticDestinationTest extends UnitTestCase {

  public function testControllerHasNoDestinationSelection(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringNotContainsString('destinationKey', $source);
    self::assertStringNotContainsString('destination_key', $source);
  }

}
