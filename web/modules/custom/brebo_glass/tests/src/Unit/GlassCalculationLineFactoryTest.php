<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit;

use Drupal\brebo_glass\Service\GlassCalculationLineFactory;
use PHPUnit\Framework\TestCase;

final class GlassCalculationLineFactoryTest extends TestCase {

  public function testBuildsMaterialInstallationAndHandlingLines(): void {
    $factory = new GlassCalculationLineFactory();
    $lines = $factory->build([
      'id' => 42,
      'position_code' => 'G-01',
      'technical_status' => 'approved',
      'quantity' => 3,
      'area_m2' => 1.25,
      'estimated_weight_kg' => 31.5,
      'composition' => '44.2-15-6',
    ]);

    self::assertCount(3, $lines);
    self::assertSame('glass', $lines[0]['key']);
    self::assertSame(3.75, $lines[0]['quantity']);
    self::assertSame('m2', $lines[0]['unit']);
    self::assertSame(3, $lines[1]['quantity']);
    self::assertSame('st', $lines[1]['unit']);
    self::assertSame(94.5, $lines[2]['quantity']);
    self::assertSame('kg', $lines[2]['unit']);
    self::assertSame('42', $lines[2]['source_reference']);
  }

  public function testRejectsUnapprovedPosition(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new GlassCalculationLineFactory())->build([
      'id' => 42,
      'position_code' => 'G-01',
      'technical_status' => 'measured',
      'quantity' => 1,
      'area_m2' => 1.0,
    ]);
  }

}
