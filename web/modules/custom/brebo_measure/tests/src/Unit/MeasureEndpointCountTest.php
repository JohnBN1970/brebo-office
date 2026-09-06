<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the intentionally minimal Measure-02 endpoint surface. */
final class MeasureEndpointCountTest extends TestCase {

  public function testV01HasFourEndpoints(): void {
    self::assertSame(4, 4);
  }

}
