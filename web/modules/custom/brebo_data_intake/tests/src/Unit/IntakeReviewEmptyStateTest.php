<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies an empty review queue is presented as a healthy state. */
final class IntakeReviewEmptyStateTest extends UnitTestCase {

  public function testControllerHasHumanEmptyState(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Controller/IntakeReviewController.php');
    self::assertIsString($source);
    self::assertStringContainsString('er staat niets te wachten op menselijke controle', $source);
  }

}
