<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies operator-facing workbench copy remains translatable. */
final class IntakeReviewTranslationTest extends UnitTestCase {

  public function testControllerUsesTranslationForLabels(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$this->t('Wat moet ik doen?')", $source);
    self::assertStringContainsString("$this->t('Office denkt')", $source);
    self::assertStringContainsString("$this->t('Hoort bij')", $source);
  }

}
