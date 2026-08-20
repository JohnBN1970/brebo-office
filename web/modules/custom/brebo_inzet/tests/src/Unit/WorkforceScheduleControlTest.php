<?php

declare(strict_types=1);

namespace Drupal\Tests\brebo_inzet\Unit;

use Drupal\brebo_inzet\Service\WorkforceScheduleControl;
use PHPUnit\Framework\TestCase;

final class WorkforceScheduleControlTest extends TestCase {

  public function testDetectsOverlappingShift(): void {
    $control = new WorkforceScheduleControl();
    $result = $control->analyze([
      1 => ['resource' => 'contact:1', 'start' => '2026-08-20T08:00:00', 'end' => '2026-08-20T12:00:00', 'open' => FALSE],
      2 => ['resource' => 'contact:1', 'start' => '2026-08-20T11:00:00', 'end' => '2026-08-20T15:00:00', 'open' => FALSE],
    ], []);

    self::assertSame(2, $result['conflicts']);
    self::assertContains('Overlappende dienst', $result['shifts'][1]['conflicts']);
    self::assertContains('Overlappende dienst', $result['shifts'][2]['conflicts']);
  }

  public function testDetectsUnavailability(): void {
    $control = new WorkforceScheduleControl();
    $result = $control->analyze([
      1 => ['resource' => 'contact:1', 'start' => '2026-08-20T08:00:00', 'end' => '2026-08-20T16:00:00', 'open' => FALSE],
    ], [
      ['resource' => 'contact:1', 'start' => '2026-08-20', 'end' => '2026-08-20', 'type' => 'Verlof'],
    ]);

    self::assertSame(1, $result['conflicts']);
    self::assertContains('Overlap met niet-beschikbaarheid', $result['shifts'][1]['conflicts']);
  }

  public function testOpenShiftIsNotMissingResourceConflict(): void {
    $control = new WorkforceScheduleControl();
    $result = $control->analyze([
      1 => ['resource' => '', 'start' => '2026-08-20T08:00:00', 'end' => '2026-08-20T16:00:00', 'open' => TRUE],
    ], []);

    self::assertSame(0, $result['conflicts']);
    self::assertSame(1, $result['open']);
  }

}
