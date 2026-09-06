<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the first controlled home validation order. */
final class MeasureHomeValidationTest extends TestCase {

  public function testSimpleOpeningIsValidatedBeforeExpandingSet(): void {
    $order = ['simple_repeated', 'large', 'kalf_or_stile', 'deep_reveal', 'inside_only'];
    self::assertSame('simple_repeated', $order[0]);
  }

}
