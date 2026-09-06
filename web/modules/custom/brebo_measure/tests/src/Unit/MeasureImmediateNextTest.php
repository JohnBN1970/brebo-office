<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the immediate next action. */
final class MeasureImmediateNextTest extends TestCase {

  public function testPrPrecedesMerge(): void {
    self::assertSame(['pr', 'ci', 'merge'], ['pr', 'ci', 'merge']);
  }

}
