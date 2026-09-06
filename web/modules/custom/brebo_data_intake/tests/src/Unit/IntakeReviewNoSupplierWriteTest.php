<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review overview cannot write supplier masterdata. */
final class IntakeReviewNoSupplierWriteTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceSupplierStorage(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('supplier_repository', $source);
    self::assertStringNotContainsString('supplier_storage', $source);
  }

}
