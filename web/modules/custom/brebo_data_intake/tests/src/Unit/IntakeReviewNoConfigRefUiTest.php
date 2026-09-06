<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies source configuration references stay internal. */
final class IntakeReviewNoConfigRefUiTest extends UnitTestCase {

  public function testReviewClassesDoNotExposeConfigRef(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('config_ref', $source);
  }

}
