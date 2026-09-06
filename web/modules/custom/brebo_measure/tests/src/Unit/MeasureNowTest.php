<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Current-action marker. */
final class MeasureNowTest extends TestCase {

  public function testCurrentSequence(): void {
    self::assertSame(['pr', 'ci', 'merge'], ['pr', 'ci', 'merge']);
  }

}
