<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the first queue asks for review instead of guessing a domain action. */
final class IntakeReviewNoHardcodedActionTest extends UnitTestCase {

  public function testControllerOnlyAsksForControl(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString("$this->t('Controleren')", $source);
    self::assertStringNotContainsString('Boeken', $source);
    self::assertStringNotContainsString('Publiceren', $source);
  }

}
