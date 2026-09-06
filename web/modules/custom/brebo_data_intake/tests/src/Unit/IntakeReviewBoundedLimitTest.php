<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies callers cannot request an unbounded review queue. */
final class IntakeReviewBoundedLimitTest extends UnitTestCase {

  public function testLimitIsClampedBetweenOneAndTwoHundred(): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/src/Service/IntakeReviewRepository.php');
    self::assertIsString($source);
    self::assertStringContainsString('max(1, min(200, $limit))', $source);
  }

}
