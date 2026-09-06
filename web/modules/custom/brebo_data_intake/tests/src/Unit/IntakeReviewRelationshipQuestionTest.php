<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench makes the canonical relation question explicit. */
final class IntakeReviewRelationshipQuestionTest extends UnitTestCase {

  public function testTableShowsWhereItemBelongs(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('Hoort bij', $source);
    self::assertStringContainsString('Nog te koppelen', $source);
  }

}
