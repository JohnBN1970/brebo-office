<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the Measure-02 PR intent. */
final class MeasurePrTitleTest extends TestCase {

  public function testTitleMentionsOfficeWorkflowApi(): void {
    $title = 'Measure: add Office workflow API for mobile capture';
    self::assertStringContainsString('Office workflow API', $title);
  }

}
