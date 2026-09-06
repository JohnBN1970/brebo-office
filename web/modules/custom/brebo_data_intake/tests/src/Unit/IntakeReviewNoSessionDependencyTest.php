<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies queue contents do not depend on session-local state. */
final class IntakeReviewNoSessionDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotUseSessionStorage(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('session', strtolower($source));
  }

}
