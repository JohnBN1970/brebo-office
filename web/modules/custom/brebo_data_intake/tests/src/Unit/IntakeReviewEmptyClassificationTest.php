<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies missing classification is explicit rather than silently blank. */
final class IntakeReviewEmptyClassificationTest extends UnitTestCase {

  public function testMissingClassificationHasHumanLabel(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$classification !== '' ? $classification : $this->t('Nog te bepalen')", $source);
  }

}
