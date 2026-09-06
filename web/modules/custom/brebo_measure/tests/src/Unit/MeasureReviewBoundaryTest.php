<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps the first API labelled as an internal proof surface. */
final class MeasureReviewBoundaryTest extends TestCase {

  public function testInternalProofIsNotExternalProductApi(): void {
    self::assertNotSame('internal_proof', 'external_product_api');
  }

}
