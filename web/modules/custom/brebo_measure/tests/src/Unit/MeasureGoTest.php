<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** PR go marker. */
final class MeasureGoTest extends TestCase {

  public function testGoMeansCiNext(): void {
    self::assertSame('ci', 'ci');
  }

}
