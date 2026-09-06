<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_data_intake\Unit;

use Drupal\Tests\UnitTestCase;

/** Verifies the new workbench does not replace the proven upload adapter. */
final class IntakeReviewUploadPreservedTest extends UnitTestCase {

  public function testUploadRouteRemainsAvailable(): void {
    $routing = file_get_contents(dirname(__DIR__, 3) . '/brebo_data_intake.routing.yml');
    self::assertIsString($routing);
    self::assertStringContainsString("path: '/brebo-office/intake/upload'", $routing);
    self::assertStringContainsString('SourceNeutralUploadForm', $routing);
  }

}
