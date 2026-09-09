<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit\Service;

use Drupal\brebo_glass\Service\GlassCalculationRouteResolver;
use PHPUnit\Framework\TestCase;

final class GlassCalculationRouteResolverTest extends TestCase {

  public function testVerticalFourSidedDoubleGlassUsesTableRoute(): void {
    $result = (new GlassCalculationRouteResolver())->resolve([
      'width_mm' => 1200,
      'height_mm' => 1500,
      'glass_type' => 'insulating_double',
      'orientation_deg' => 90,
      'support' => 'four_sided',
      'application' => 'standard',
    ]);
    self::assertSame('ready', $result['status']);
    self::assertSame('verified_table_route', $result['route']);
    self::assertFalse($result['production_release']);
  }

  public function testTripleGlassRequiresConstructiveRoute(): void {
    $result = (new GlassCalculationRouteResolver())->resolve([
      'width_mm' => 1200,
      'height_mm' => 1500,
      'glass_type' => 'insulating_triple',
      'orientation_deg' => 90,
      'support' => 'four_sided',
      'application' => 'standard',
    ]);
    self::assertSame('technical_calculation_required', $result['status']);
    self::assertSame('constructive_route', $result['route']);
    self::assertFalse($result['production_release']);
  }

  public function testOverheadGlassNeverFallsIntoSimpleTableRoute(): void {
    $result = (new GlassCalculationRouteResolver())->resolve([
      'width_mm' => 900,
      'height_mm' => 1400,
      'glass_type' => 'pvb_laminated',
      'orientation_deg' => 30,
      'support' => 'four_sided',
      'application' => 'overhead',
    ]);
    self::assertSame('constructive_route', $result['route']);
    self::assertFalse($result['production_release']);
  }

  public function testMissingInputFailsClosed(): void {
    $result = (new GlassCalculationRouteResolver())->resolve([]);
    self::assertSame('blocked', $result['status']);
    self::assertSame('needs_input', $result['route']);
    self::assertFalse($result['production_release']);
  }

}
