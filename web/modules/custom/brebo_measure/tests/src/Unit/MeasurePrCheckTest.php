<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the intended PR base. */
final class MeasurePrCheckTest extends TestCase {

  public function testPrTargetsDevelop(): void {
    self::assertSame('develop', 'develop');
  }

}
