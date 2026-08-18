<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_glass\Unit;

use Drupal\brebo_glass\Service\GlassTechnicalRuleEvaluator;
use PHPUnit\Framework\TestCase;

final class GlassTechnicalRuleEvaluatorTest extends TestCase {

  private GlassTechnicalRuleEvaluator $evaluator;

  protected function setUp(): void {
    $this->evaluator = new GlassTechnicalRuleEvaluator();
  }

  public function testStandardVerifiedPositionPasses(): void {
    $result = $this->evaluator->evaluate([
      'wind_check_required' => TRUE,
      'wind_check_state' => 'passed',
      'application_type' => 'standard',
      'glass_type' => 'insulating',
      'measurement_verified' => TRUE,
    ]);

    self::assertSame('passed', $result['state']);
    self::assertSame([], $result['issues']);
  }

  public function testUnverifiedMeasurementBlocksApproval(): void {
    $result = $this->evaluator->evaluate([
      'wind_check_required' => TRUE,
      'wind_check_state' => 'passed',
      'application_type' => 'standard',
      'glass_type' => 'insulating',
      'measurement_verified' => FALSE,
    ]);

    self::assertSame('blocked', $result['state']);
  }

  public function testDoorWithoutSafetyGlassIsBlocked(): void {
    $result = $this->evaluator->evaluate([
      'wind_check_required' => TRUE,
      'wind_check_state' => 'passed',
      'application_type' => 'door',
      'glass_type' => 'insulating',
      'measurement_verified' => TRUE,
      'performance_declaration_ref' => 'DOP-123',
    ]);

    self::assertSame('blocked', $result['state']);
    self::assertNotEmpty($result['issues']);
  }

  public function testFallProtectionAlwaysNeedsExpertReview(): void {
    $result = $this->evaluator->evaluate([
      'wind_check_required' => TRUE,
      'wind_check_state' => 'passed',
      'application_type' => 'fall_protection',
      'glass_type' => 'laminated',
      'measurement_verified' => TRUE,
      'performance_declaration_ref' => 'DOP-456',
    ]);

    self::assertSame('expert_review', $result['state']);
  }

  public function testFireSeparationNeedsClassification(): void {
    $result = $this->evaluator->evaluate([
      'wind_check_required' => TRUE,
      'wind_check_state' => 'passed',
      'application_type' => 'fire_separation',
      'glass_type' => 'fire_resistant',
      'measurement_verified' => TRUE,
      'performance_declaration_ref' => 'DOP-789',
    ]);

    self::assertSame('blocked', $result['state']);
  }

}
