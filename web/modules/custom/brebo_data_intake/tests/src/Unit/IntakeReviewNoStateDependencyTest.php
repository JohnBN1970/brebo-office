<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench derives state from canonical intake records. */
final class IntakeReviewNoStateDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotUseDrupalState(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('state', strtolower($source));
  }

}
