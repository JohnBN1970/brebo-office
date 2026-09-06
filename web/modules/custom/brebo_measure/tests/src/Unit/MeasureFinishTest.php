<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Finish marker. */
final class MeasureFinishTest extends TestCase {

  public function testNextOperationIsPullRequest(): void {
    self::assertSame('pull_request', 'pull_request');
  }

}
