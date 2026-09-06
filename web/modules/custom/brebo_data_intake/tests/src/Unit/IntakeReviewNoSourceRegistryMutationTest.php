<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies presentation code cannot register or rename sources. */
final class IntakeReviewNoSourceRegistryMutationTest extends UnitTestCase {

  public function testReviewClassesDoNotRegisterSources(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('registerSource', $source);
  }

}
