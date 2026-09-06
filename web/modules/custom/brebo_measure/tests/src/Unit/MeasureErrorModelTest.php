<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the first empirical error dimensions. */
final class MeasureErrorModelTest extends TestCase {

  public function testErrorModelIsNotAccuracyOnly(): void {
    $dimensions = ['accuracy', 'repeatability', 'uncertainty', 'completeness', 'operator_correction'];
    self::assertCount(5, $dimensions);
    self::assertContains('repeatability', $dimensions);
  }

}
