<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Smoke test for the documented first Measure client vocabulary. */
final class MeasureApiDocumentationTest extends TestCase {

  public function testFirstClientVocabulary(): void {
    $fields = ['source_type', 'device_model', 'software_version', 'confidence', 'uncertainty_mm'];
    self::assertContains('source_type', $fields);
    self::assertContains('confidence', $fields);
    self::assertContains('uncertainty_mm', $fields);
  }

}
