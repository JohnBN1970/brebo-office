<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\WorkforcePlanningOptimizer;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\WorkforcePlanningOptimizer
 */
final class WorkforcePlanningOptimizerTest extends TestCase {

  private WorkforcePlanningOptimizer $optimizer;

  protected function setUp(): void {
    parent::setUp();
    $this->optimizer = new WorkforcePlanningOptimizer();
  }

  public function testHardConstraintsExcludeCandidate(): void {
    $result = $this->optimizer->rank([[
      'label' => 'Persoon A', 'available' => FALSE, 'qualification_status' => 'Passend',
      'overlap' => FALSE, 'remaining_hours' => 40,
    ]], 8);
    self::assertFalse($result[0]['eligible']);
    self::assertStringContainsString('niet beschikbaar', $result[0]['explanation']);
  }

  public function testQualificationBlockCannotBeOutscored(): void {
    $result = $this->optimizer->rank([
      [
        'label' => 'Onbevoegd dichtbij', 'available' => TRUE, 'qualification_status' => 'Blokkade',
        'overlap' => FALSE, 'remaining_hours' => 40, 'distance_km' => 1, 'quality_score' => 100,
      ],
      [
        'label' => 'Bevoegd verder', 'available' => TRUE, 'qualification_status' => 'Passend',
        'overlap' => FALSE, 'remaining_hours' => 40, 'distance_km' => 30, 'quality_score' => 60,
      ],
    ], 8);
    self::assertSame('Bevoegd verder', $result[0]['label']);
    self::assertTrue($result[0]['eligible']);
  }

  public function testContinuityAndTravelInfluenceRanking(): void {
    $result = $this->optimizer->rank([
      [
        'label' => 'Bekende kracht', 'available' => TRUE, 'qualification_status' => 'Passend',
        'overlap' => FALSE, 'remaining_hours' => 40, 'distance_km' => 15,
        'quality_score' => 80, 'continuity' => TRUE,
      ],
      [
        'label' => 'Nieuwe kracht', 'available' => TRUE, 'qualification_status' => 'Passend',
        'overlap' => FALSE, 'remaining_hours' => 40, 'distance_km' => 5,
        'quality_score' => 80, 'continuity' => FALSE,
      ],
    ], 8);
    self::assertSame('Bekende kracht', $result[0]['label']);
    self::assertStringContainsString('projectcontinuïteit', $result[0]['explanation']);
  }

  public function testCapacityShortageBlocksCandidate(): void {
    $result = $this->optimizer->rank([[
      'label' => 'Persoon A', 'available' => TRUE, 'qualification_status' => 'Passend',
      'overlap' => FALSE, 'remaining_hours' => 4,
    ]], 8);
    self::assertFalse($result[0]['eligible']);
    self::assertStringContainsString('onvoldoende resterende capaciteit', $result[0]['explanation']);
  }

  public function testForecastShowsShortageAndCoverage(): void {
    $result = $this->optimizer->forecast([
      ['week' => '2026-W34', 'demand_hours' => 100, 'staffed_hours' => 80],
      ['week' => '2026-W35', 'demand_hours' => 40, 'staffed_hours' => 48],
    ]);
    self::assertSame('Tekort', $result[0]['status']);
    self::assertSame(-20.0, $result[0]['gap_hours']);
    self::assertSame(80.0, $result[0]['coverage_percent']);
    self::assertSame('Ruimte', $result[1]['status']);
  }

}
