<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies missing source description is explicit rather than silently blank. */
final class IntakeReviewEmptySubjectTest extends UnitTestCase {

  public function testMissingSubjectHasHumanLabel(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$subject !== '' ? $subject : $this->t('Zonder omschrijving')", $source);
  }

}
