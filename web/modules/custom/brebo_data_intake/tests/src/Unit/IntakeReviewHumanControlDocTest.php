<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench purpose remains human control, not automation bypass. */
final class IntakeReviewHumanControlDocTest extends UnitTestCase {

  public function testReadmeNamesHumanDecisionFlow(): void {
    $readme = file_get_contents(dirname(__DIR__, 3) . '/README.md');
    self::assertIsString($readme);
    self::assertStringContainsString('mensentaal', $readme);
    self::assertStringContainsString('Accepteren, corrigeren en afwijzen', $readme);
  }

}
