<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench is organized around the operator's next action. */
final class IntakeReviewWorkQuestionTest extends UnitTestCase {

  public function testTableAsksWhatToDoNext(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('Wat moet ik doen?', $source);
    self::assertStringContainsString('Controleren', $source);
  }

}
