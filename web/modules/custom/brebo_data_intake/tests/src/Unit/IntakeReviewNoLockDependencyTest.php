<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the read-only workbench does not acquire intake mutation locks. */
final class IntakeReviewNoLockDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotUseLockBackend(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('LockBackendInterface', $source);
    self::assertStringNotContainsString("get('lock')", $source);
  }

}
