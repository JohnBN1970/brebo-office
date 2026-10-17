<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/WorkforceGeoFence.php';
require_once dirname(__DIR__) . '/src/Inzet/ProjectClockZoneControl.php';
require_once dirname(__DIR__) . '/src/Inzet/WorkforceScheduleControl.php';
require_once dirname(__DIR__) . '/src/Inzet/PausePolicy.php';
require_once dirname(__DIR__) . '/src/Inzet/ClockTimeControl.php';

use Brebo\Office\Inzet\WorkforceGeoFence;
use Brebo\Office\Inzet\ProjectClockZoneControl;
use Brebo\Office\Inzet\WorkforceScheduleControl;
use Brebo\Office\Inzet\PausePolicy;
use Brebo\Office\Inzet\ClockTimeControl;

$check = static function (bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
};
$geo = new WorkforceGeoFence();
$check($geo->assess(52.0, 5.0, 52.0, 5.0)['status'] === 'Binnen zone', 'same point');
$check($geo->assess(52.0, 5.0, 53.0, 5.0)['status'] === 'Buiten zone', 'distant point');
$check($geo->assess(null, 5.0, 52.0, 5.0)['status'] === 'Geen locatie', 'missing location');
$zones = new ProjectClockZoneControl($geo);
$check($zones->assess([['id' => 7, 'latitude' => 52.0, 'longitude' => 5.0]], 52.0, 5.0)['matched_zone_id'] === 7, 'zone match');
$check($zones->assess([], 52.0, 5.0)['status'] === 'Geen werkzone', 'empty zones');
$roster = new WorkforceScheduleControl();
$analysis = $roster->analyze([
  1 => ['resource' => 'A', 'start' => '2026-10-16T08:00:00', 'end' => '2026-10-16T12:00:00'],
  2 => ['resource' => 'A', 'start' => '2026-10-16T10:00:00', 'end' => '2026-10-16T15:00:00'],
], []);
$check($analysis['conflicts'] === 2, 'overlapping shifts');
$pause = new PausePolicy();
$check($pause->requiresPauseRegistration('required'), 'required pause');
$check(!$pause->showsPauseControls('off'), 'pause disabled');
$clock = new ClockTimeControl();
$start = new DateTimeImmutable('2026-10-16T08:00:00');
$end = new DateTimeImmutable('2026-10-16T17:00:00');
$check($clock->assess($start, $end, $start, new DateTimeImmutable('2026-10-16T16:45:00'))['status'] === 'Te vroeg uitgeklokt', 'early clock out');
echo "Office Inzet pure logic OK\n";
