<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the workbench has no mail module dependency. */
final class IntakeReviewNoMailDependencyTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceMailModule(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('brebo_mail', $source);
    self::assertStringNotContainsString('mail_intake', $source);
  }

}
