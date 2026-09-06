<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench stays part of the central intake module. */
final class IntakeReviewNoNewModuleTest extends UnitTestCase {

  public function testReviewClassesUseDataIntakeNamespace(): void {
    $root = dirname(__DIR__, 3);
    $repository = file_get_contents($root . '/src/Service/IntakeReviewRepository.php');
    $controller = file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringContainsString('namespace Drupal\\brebo_data_intake', (string) $repository);
    self::assertStringContainsString('namespace Drupal\\brebo_data_intake', (string) $controller);
  }

}
