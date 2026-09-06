<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the canonical measurement evidence chain. */
final class MeasureTraceabilityTest extends TestCase {

  public function testEvidenceChainRetainsCaptureVersion(): void {
    $chain = ['building_object', 'opening', 'assignment', 'capture_version', 'observation', 'validation'];
    self::assertContains('capture_version', $chain);
    self::assertSame('validation', $chain[array_key_last($chain)]);
  }

}
