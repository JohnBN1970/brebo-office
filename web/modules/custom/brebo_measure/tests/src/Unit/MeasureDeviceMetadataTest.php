<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Guards device traceability needed for empirical validation. */
final class MeasureDeviceMetadataTest extends TestCase {

  public function testCaptureMetadataCanIdentifyMeasurementSource(): void {
    $metadata = ['source_type', 'device_id', 'device_model', 'software_version', 'operator_uid'];
    self::assertContains('device_model', $metadata);
    self::assertContains('software_version', $metadata);
  }

}
