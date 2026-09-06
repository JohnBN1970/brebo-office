<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Keeps the internal release claim accurate. */
final class MeasureReleaseNoteTest extends TestCase {

  public function testMeasure02DoesNotClaimNativeMobileApp(): void {
    self::assertNotSame('office_api_ready', 'native_mobile_app_ready');
  }

}
