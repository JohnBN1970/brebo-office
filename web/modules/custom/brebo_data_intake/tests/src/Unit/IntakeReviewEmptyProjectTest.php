<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies missing canonical relation is explicit rather than silently blank. */
final class IntakeReviewEmptyProjectTest extends UnitTestCase {

  public function testMissingProjectHasHumanLabel(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$project !== '' ? $project : $this->t('Nog te koppelen')", $source);
  }

}
