<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the operator queue does not depend on cron to render. */
final class IntakeReviewNoCronDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceCron(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('cron', strtolower($source));
  }

}
