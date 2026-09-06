<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench does not couple to Moneybird or another provider. */
final class IntakeReviewNoMoneybirdDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceMoneybird(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('Moneybird', $source);
    self::assertStringNotContainsString('moneybird', $source);
  }

}
