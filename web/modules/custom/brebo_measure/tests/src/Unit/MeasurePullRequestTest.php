<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Pull-request marker test. */
final class MeasurePullRequestTest extends TestCase {

  public function testTargetIsDevelop(): void {
    self::assertSame('develop', 'develop');
  }

}
