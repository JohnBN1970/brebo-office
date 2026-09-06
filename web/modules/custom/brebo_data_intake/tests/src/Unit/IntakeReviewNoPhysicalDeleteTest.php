<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies physical source deletion remains outside intake review. */
final class IntakeReviewNoPhysicalDeleteTest extends UnitTestCase {

  public function testReviewClassesHaveNoUnlinkOrFileDelete(): void {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root . '/src/Service/IntakeReviewRepository.php') . file_get_contents($root . '/src/Controller/IntakeReviewController.php');
    self::assertStringNotContainsString('unlink(', $source);
    self::assertStringNotContainsString('file_delete', $source);
  }

}
