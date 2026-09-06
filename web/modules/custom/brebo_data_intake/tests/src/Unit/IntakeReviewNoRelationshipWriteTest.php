<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the review overview cannot write relationship masterdata. */
final class IntakeReviewNoRelationshipWriteTest extends UnitTestCase {

  public function testReviewClassesDoNotReferenceRelationshipStorage(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('brebo_relationship', $source);
    self::assertStringNotContainsString('brebo_organization', $source);
  }

}
