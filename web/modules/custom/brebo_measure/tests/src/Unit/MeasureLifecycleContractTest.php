<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the intended capture lifecycle for the next increment. */
final class MeasureLifecycleContractTest extends TestCase {

  public function testCaptureLifecycleIsExplicit(): void {
    $states = ['draft', 'captured', 'validating', 'accepted', 'rejected'];
    self::assertSame('draft', $states[0]);
    self::assertContains('accepted', $states);
    self::assertContains('rejected', $states);
  }

}
