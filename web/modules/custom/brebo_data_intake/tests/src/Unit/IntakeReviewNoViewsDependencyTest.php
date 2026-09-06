<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench does not depend on Views plugin discovery. */
final class IntakeReviewNoViewsDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceViews(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('views', strtolower($source));
  }

}
