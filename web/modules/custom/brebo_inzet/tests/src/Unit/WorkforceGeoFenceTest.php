<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_inzet\Unit;

use Drupal\brebo_inzet\Service\WorkforceGeoFence;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_inzet\Service\WorkforceGeoFence
 */
final class WorkforceGeoFenceTest extends TestCase {

  public function testSamePointIsInsideZone(): void {
    $result = (new WorkforceGeoFence())->assess(52.3702, 4.8952, 52.3702, 4.8952);
    self::assertSame('Binnen zone', $result['status']);
    self::assertSame(150.0, $result['configured_radius']);
    self::assertSame(0.0, $result['distance']);
  }

  public function testCustomRadiusControlsAcceptance(): void {
    $fence = new WorkforceGeoFence();
    $small = $fence->assess(52.3702, 4.8952, 52.3711, 4.8952, 50.0);
    $large = $fence->assess(52.3702, 4.8952, 52.3711, 4.8952, 150.0);
    self::assertSame('Buiten zone', $small['status']);
    self::assertSame('Binnen zone', $large['status']);
  }

  public function testShiftRadiusOverridesBuildingRadius(): void {
    $fence = new WorkforceGeoFence();
    self::assertSame(75.0, $fence->resolveRadius(75.0, 250.0));
    self::assertSame(250.0, $fence->resolveRadius(NULL, 250.0));
    self::assertSame(150.0, $fence->resolveRadius(NULL, NULL));
  }

  public function testAccuracyExtendsEffectiveRadius(): void {
    $result = (new WorkforceGeoFence())->assess(52.3702, 4.8952, 52.3720, 4.8952, 150.0, 75.0);
    self::assertSame('Binnen zone', $result['status']);
    self::assertSame(225.0, $result['effective_radius']);
  }

  public function testMissingLocationIsExplicit(): void {
    $result = (new WorkforceGeoFence())->assess(52.3702, 4.8952, NULL, NULL, 100.0);
    self::assertSame('Geen locatie', $result['status']);
    self::assertSame(100.0, $result['configured_radius']);
  }

}
