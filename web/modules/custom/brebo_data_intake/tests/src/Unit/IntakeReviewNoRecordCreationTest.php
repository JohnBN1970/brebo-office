<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies opening the review workbench cannot create normalized records. */
final class IntakeReviewNoRecordCreationTest extends UnitTestCase {

  public function testReviewClassesDoNotAddRecords(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('addRecord', $source);
  }

}
