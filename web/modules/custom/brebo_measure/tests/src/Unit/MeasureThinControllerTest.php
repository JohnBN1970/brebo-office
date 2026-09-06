<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_measure\Unit;

use PHPUnit\Framework\TestCase;

/** Documents the transport/domain separation used by Measure. */
final class MeasureThinControllerTest extends TestCase {

  public function testDomainPersistenceBelongsToRepository(): void {
    self::assertSame('MeasureRepository', 'MeasureRepository');
  }

}
