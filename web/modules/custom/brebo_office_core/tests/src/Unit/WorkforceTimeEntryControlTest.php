<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\WorkforceTimeEntryControl;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\WorkforceTimeEntryControl
 * @group brebo_office_core
 */
final class WorkforceTimeEntryControlTest extends TestCase {

  /** @covers ::assess */
  public function testCleanEntryIsAccepted(): void {
    $result = (new WorkforceTimeEntryControl())->assess([
      'planned_start' => '2026-08-18 07:00:00',
      'planned_end' => '2026-08-18 15:30:00',
      'actual_start' => '2026-08-18 07:05:00',
      'actual_end' => '2026-08-18 15:25:00',
      'break_minutes' => 30,
      'actual_hours' => 7.83,
      'clock_types' => ['In', 'Pauze start', 'Pauze einde', 'Uit'],
      'geo_statuses' => ['Binnen zone', 'Binnen zone'],
      'budget_hours' => 80,
      'approved_budget_hours' => 40,
    ]);

    self::assertSame('Akkoord', $result['status']);
    self::assertSame([], $result['blocking']);
    self::assertSame([], $result['deviations']);
  }

  /** @covers ::assess */
  public function testMissingClockAndBudgetOverrunBlockApproval(): void {
    $result = (new WorkforceTimeEntryControl())->assess([
      'planned_start' => '2026-08-18 07:00:00',
      'planned_end' => '2026-08-18 15:30:00',
      'actual_start' => '2026-08-18 07:30:00',
      'actual_end' => '2026-08-18 17:00:00',
      'actual_hours' => 9.0,
      'clock_types' => ['In'],
      'geo_statuses' => ['Buiten zone'],
      'budget_hours' => 45,
      'approved_budget_hours' => 40,
    ]);

    self::assertSame('Blokkade', $result['status']);
    self::assertContains('Uitklokregistratie ontbreekt.', $result['blocking']);
    self::assertContains('Goedkeuring zou het vrijgegeven urenbudget overschrijden.', $result['blocking']);
    self::assertNotEmpty($result['deviations']);
  }

}
