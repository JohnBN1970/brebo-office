<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the minimum KPIs for the first real opening test. */
final class MeasureFieldKpiTest extends TestCase {

  public function testFieldKpisIncludeTimeAccuracyAndRework(): void {
    $kpis = ['capture_seconds', 'difference_mm', 'corrections', 'second_visit', 'office_review_seconds'];
    self::assertContains('capture_seconds', $kpis);
    self::assertContains('difference_mm', $kpis);
    self::assertContains('second_visit', $kpis);
  }

}
