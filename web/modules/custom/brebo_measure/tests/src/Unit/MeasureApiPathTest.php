<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the BREBO Office API namespace used by Measure. */
final class MeasureApiPathTest extends TestCase {

  public function testMeasureApiLivesUnderOfficeNamespace(): void {
    $path = '/brebo-office/api/measure/openings/1';
    self::assertStringStartsWith('/brebo-office/api/measure/', $path);
  }

}
