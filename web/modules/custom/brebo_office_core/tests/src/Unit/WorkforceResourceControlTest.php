<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\WorkforceResourceControl;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\WorkforceResourceControl
 */
final class WorkforceResourceControlTest extends TestCase {

  private WorkforceResourceControl $control;

  protected function setUp(): void {
    parent::setUp();
    $this->control = new WorkforceResourceControl();
  }

  public function testHealthyAvailableResourceIsReleased(): void {
    $result = $this->control->assess(
      ['active' => TRUE, 'status' => 'Beschikbaar', 'inspection_until' => '2027-01-01', 'maintenance_due' => '2027-01-01'],
      ['start' => '2026-08-20 07:00', 'end' => '2026-08-20 16:00'],
      [],
    );
    self::assertSame('Vrijgegeven', $result['status']);
  }

  public function testOverlappingReservationBlocks(): void {
    $result = $this->control->assess(
      ['active' => TRUE, 'status' => 'Beschikbaar'],
      ['start' => '2026-08-20 07:00', 'end' => '2026-08-20 16:00'],
      [['start' => '2026-08-20 12:00', 'end' => '2026-08-20 18:00', 'status' => 'Bevestigd']],
    );
    self::assertSame('Blokkade', $result['status']);
    self::assertContains('middel is in dezelfde periode al gereserveerd', $result['blocks']);
  }

  public function testExpiredInspectionAndMaintenanceBlock(): void {
    $result = $this->control->assess(
      ['active' => TRUE, 'status' => 'Beschikbaar', 'inspection_until' => '2026-08-19', 'maintenance_due' => '2026-08-01'],
      ['start' => '2026-08-20 07:00', 'end' => '2026-08-20 16:00'],
      [],
    );
    self::assertContains('keuring is verlopen', $result['blocks']);
    self::assertContains('onderhoud is achterstallig', $result['blocks']);
  }

  public function testDefectResourceBlocks(): void {
    $result = $this->control->assess(
      ['active' => TRUE, 'status' => 'Defect'],
      ['start' => '2026-08-20 07:00', 'end' => '2026-08-20 16:00'],
      [],
    );
    self::assertSame('Blokkade', $result['status']);
  }

  public function testMissingDriverQualificationBlocksRequiredSkill(): void {
    $result = $this->control->assess(
      ['active' => TRUE, 'status' => 'Beschikbaar', 'requires_skill' => TRUE],
      ['start' => '2026-08-20 07:00', 'end' => '2026-08-20 16:00'],
      [],
      'Blokkade',
    );
    self::assertContains('bestuurder mist de vereiste bevoegdheid', $result['blocks']);
  }

  public function testUpcomingDeadlinesWarn(): void {
    $result = $this->control->assess(
      ['active' => TRUE, 'status' => 'Beschikbaar', 'inspection_until' => '2026-09-01', 'maintenance_due' => '2026-08-25'],
      ['start' => '2026-08-20 07:00', 'end' => '2026-08-20 16:00'],
      [],
    );
    self::assertSame('Waarschuwing', $result['status']);
    self::assertCount(2, $result['warnings']);
  }

}
