<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies ordinary review rendering does not emit audit-like log side effects. */
final class IntakeReviewNoLoggerSideEffectTest extends UnitTestCase {

  public function testReviewClassesDoNotLogPageViews(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('logger', strtolower($source));
  }

}
