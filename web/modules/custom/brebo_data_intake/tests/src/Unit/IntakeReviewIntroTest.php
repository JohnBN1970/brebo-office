<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench explains the human-control boundary. */
final class IntakeReviewIntroTest extends UnitTestCase {

  public function testIntroExplainsSourceAndDecisionFlow(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('oorspronkelijke bron blijft leidend', $source);
    self::assertStringContainsString('wat het is, waar het bij hoort en wat ermee moet gebeuren', $source);
  }

}
