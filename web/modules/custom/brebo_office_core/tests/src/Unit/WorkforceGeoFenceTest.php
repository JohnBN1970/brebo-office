<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\WorkforceGeoFence;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\WorkforceGeoFence
 */
final class WorkforceGeoFenceTest extends TestCase {

  public function testSamePointIsInsideZone(): void {
    $result = (new WorkforceGeoFence())->assess(52.3702, 4.8952, 52.3702, 4.8952);
    self::assertSame('Binnen zone', $result['status']);
    self::assertSame(0.0, $result['distance']);
  }

  public function testDistantPointIsOutsideZone(): void {
    $result = (new WorkforceGeoFence())->assess(52.3702, 4.8952, 52.3802, 4.8952, 150);
    self::assertSame('Buiten zone', $result['status']);
    self::assertGreaterThan(1000, $result['distance']);
  }

  public function testAccuracyExtendsEffectiveRadius(): void {
    $result = (new WorkforceGeoFence())->assess(52.3702, 4.8952, 52.3720, 4.8952, 150, 75);
    self::assertSame('Binnen zone', $result['status']);
  }

  public function testMissingLocationIsExplicit(): void {
    $result = (new WorkforceGeoFence())->assess(52.3702, 4.8952, NULL, NULL);
    self::assertSame('Geen locatie', $result['status']);
  }

}
