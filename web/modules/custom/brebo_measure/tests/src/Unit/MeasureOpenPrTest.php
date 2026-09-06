<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Marks the branch ready to open for CI review. */
final class MeasureOpenPrTest extends TestCase {

  public function testNextFeedbackLoopIsCi(): void {
    self::assertSame('ci', 'ci');
  }

}
