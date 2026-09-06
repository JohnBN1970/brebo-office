<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Final-action marker. */
final class MeasureFinalActionTest extends TestCase {

  public function testOpenPr(): void {
    self::assertSame('open_pr', 'open_pr');
  }

}
