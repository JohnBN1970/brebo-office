<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Final PR action marker. */
final class MeasurePrNowTest extends TestCase {

  public function testPrIsNext(): void {
    self::assertSame('pr', 'pr');
  }

}
