<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review overview cannot write canonical building/project objects. */
final class IntakeReviewNoCanonicalWriteTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceCanonicalEntityStorage(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('brebo_building', $source);
    self::assertStringNotContainsString('brebo_project', $source);
  }

}
