<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps the first API maturity explicit. */
final class MeasureInternalApiTest extends TestCase {

  public function testInternalApiStillNeedsExternalHardening(): void {
    self::assertNotSame('internal_v0.1', 'external_stable_v1');
  }

}
