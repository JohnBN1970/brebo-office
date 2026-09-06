<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the minimum observation payload consumed by the API. */
final class MeasureApiPayloadTest extends TestCase {

  public function testObservationPayloadKeepsValueAndEvidenceMetadataSeparate(): void {
    $payload = [
      'key' => 'opening.width.middle',
      'provenance' => 'measured',
      'method' => 'lidar',
      'value' => ['value' => 1843, 'unit' => 'mm'],
      'confidence' => 0.92,
      'uncertainty_mm' => 2.0,
    ];
    self::assertSame(1843, $payload['value']['value']);
    self::assertSame('lidar', $payload['method']);
  }

}
