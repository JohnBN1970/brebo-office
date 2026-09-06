<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies machine classification is presented as a suggestion. */
final class IntakeReviewOfficeGuessTest extends UnitTestCase {

  public function testTableLabelsMachineClassificationAsOfficeGuess(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('Office denkt', $source);
  }

}
