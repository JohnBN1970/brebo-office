<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_office_core\Unit;

use Drupal\brebo_office_core\Service\WorkforceScheduleControl;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\brebo_office_core\Service\WorkforceScheduleControl
 */
final class WorkforceScheduleControlTest extends TestCase {

  public function testOverlappingShiftsConflict(): void {
    $result = (new WorkforceScheduleControl())->analyze([
      1 => $this->shift('contact:1', '2026-08-17T08:00:00', '2026-08-17T16:00:00'),
      2 => $this->shift('contact:1', '2026-08-17T15:00:00', '2026-08-17T18:00:00'),
    ], []);
    self::assertSame(2, $result['conflicts']);
  }

  public function testAdjacentShiftsDoNotConflict(): void {
    $result = (new WorkforceScheduleControl())->analyze([
      1 => $this->shift('contact:1', '2026-08-17T08:00:00', '2026-08-17T12:00:00'),
      2 => $this->shift('contact:1', '2026-08-17T12:00:00', '2026-08-17T16:00:00'),
    ], []);
    self::assertSame(0, $result['conflicts']);
  }

  public function testUnavailableResourceConflicts(): void {
    $result = (new WorkforceScheduleControl())->analyze([
      1 => $this->shift('contact:1', '2026-08-17T08:00:00', '2026-08-17T16:00:00'),
    ], [[
      'resource' => 'contact:1', 'start' => '2026-08-17', 'end' => '2026-08-18', 'type' => 'Niet beschikbaar',
    ]]);
    self::assertContains('Overlap met niet-beschikbaarheid', $result['shifts'][1]['conflicts']);
  }

  public function testUnassignedClosedShiftConflicts(): void {
    $result = (new WorkforceScheduleControl())->analyze([
      1 => $this->shift('', '2026-08-17T08:00:00', '2026-08-17T16:00:00'),
    ], []);
    self::assertContains('Geen persoon of ploeg toegewezen', $result['shifts'][1]['conflicts']);
  }

  private function shift(string $resource, string $start, string $end): array {
    return compact('resource', 'start', 'end') + ['open' => FALSE];
  }

}
