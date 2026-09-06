<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** PR-action marker. */
final class MeasurePrActionTest extends TestCase {

  public function testAction(): void {
    self::assertSame('pr', 'pr');
  }

}
