<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit;

use Drupal\brebo_glass\Service\GlassApprovalPolicy;
use PHPUnit\Framework\TestCase;

final class GlassApprovalPolicyTest extends TestCase {

  public function testCompliantMeasuredPositionCanBeApproved(): void {
    $result = (new GlassApprovalPolicy())->evaluate($this->validPosition());

    self::assertTrue($result['allowed']);
    self::assertSame([], $result['issues']);
  }

  public function testUnverifiedMeasurementBlocksApproval(): void {
    $position = $this->validPosition();
    $position['measurement_verified'] = 0;

    $result = (new GlassApprovalPolicy())->evaluate($position);

    self::assertFalse($result['allowed']);
    self::assertNotEmpty($result['issues']);
  }

  public function testWindUtilizationAboveUnityBlocksApproval(): void {
    $position = $this->validPosition();
    $position['wind_utilization'] = 1.001;

    $result = (new GlassApprovalPolicy())->evaluate($position);

    self::assertFalse($result['allowed']);
  }

  public function testMissingProductSelectionBlocksApproval(): void {
    $position = $this->validPosition();
    $position['recommended_glass_ref'] = '';

    $result = (new GlassApprovalPolicy())->evaluate($position);

    self::assertFalse($result['allowed']);
  }

  /**
   * @return array<string, mixed>
   */
  private function validPosition(): array {
    return [
      'technical_status' => 'measured',
      'technical_check_state' => 'passed',
      'measurement_verified' => 1,
      'wind_verified' => 1,
      'wind_utilization' => 0.82,
      'recommended_glass_ref' => 'GL-001 — Isolatieglas 6-16-6',
    ];
  }

}
