<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review read model has no hidden configuration dependency. */
final class IntakeReviewNoConfigDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotRequireConfigFactory(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('config.factory', $source);
    self::assertStringNotContainsString('ConfigFactoryInterface', $source);
  }

}
