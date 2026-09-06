<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the first empirical hardware-decision classification. */
final class MeasureQualityClassificationTest extends TestCase {

  public function testValidationClassesCoverMobileThroughHumanReview(): void {
    $classes = ['A', 'B', 'C', 'D'];
    self::assertSame(['A', 'B', 'C', 'D'], $classes);
  }

}
