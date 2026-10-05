<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\brebo_inzet\Contract\PersonnelAssignmentComparisonRepositoryInterface;

/** Compares planned personnel assignments with actual clock registrations. */
final class PersonnelAssignmentComparison {

  public function __construct(
    private readonly PersonnelAssignmentComparisonRepositoryInterface $comparisonRepository,
  ) {}

  /**
   * @return array{planned_hours:float,clocked_hours:float,delta_hours:float,state:string,open_session:bool}
   */
  public function compare(int $assignmentId, ?\DateTimeImmutable $now = NULL): array {
    $assignment = $this->comparisonRepository->assignment($assignmentId);
    if ($assignment === NULL) {
      return $this->result(0.0, 0.0, 'incomplete', FALSE);
    }

    $date = $assignment['date'];
    $projectId = $assignment['project_id'];
    $userId = $assignment['user_id'];
    $plannedHours = $assignment['planned_hours'];

    if ($date === '' || $projectId <= 0 || $userId <= 0) {
      return $this->result($plannedHours, 0.0, 'incomplete', FALSE);
    }

    $timezoneName = $this->comparisonRepository->timezoneName();
    $timezone = new \DateTimeZone($timezoneName !== '' ? $timezoneName : 'Europe/Brussels');
    $utc = new \DateTimeZone('UTC');
    $dayStartLocal = new \DateTimeImmutable($date . ' 00:00:00', $timezone);
    $dayEndLocal = $dayStartLocal->modify('+1 day');
    $dayStartUtc = $dayStartLocal->setTimezone($utc);
    $dayEndUtc = $dayEndLocal->setTimezone($utc);
    $now ??= new \DateTimeImmutable('now', $utc);
    $now = $now->setTimezone($utc);

    $seconds = 0;
    $openSession = FALSE;
    foreach ($this->comparisonRepository->clockSessions(
      $projectId,
      $userId,
      $dayEndUtc->format('Y-m-d\\TH:i:s'),
    ) as $clock) {
      $in = new \DateTimeImmutable($clock['clock_in'], $utc);
      $outValue = $clock['clock_out'];

      if ($outValue === NULL) {
        $out = $now < $dayEndUtc ? $now : $dayEndUtc;
        $openSession = $out > $dayStartUtc && $in < $dayEndUtc;
      }
      else {
        $out = new \DateTimeImmutable($outValue, $utc);
      }

      if ($out <= $dayStartUtc || $in >= $dayEndUtc) {
        continue;
      }

      $from = $in > $dayStartUtc ? $in : $dayStartUtc;
      $to = $out < $dayEndUtc ? $out : $dayEndUtc;
      if ($to > $from) {
        $seconds += $to->getTimestamp() - $from->getTimestamp();
      }
    }

    $clockedHours = round($seconds / 3600, 2);
    $today = $now->setTimezone($timezone)->format('Y-m-d');

    if ($date > $today) {
      $state = 'future';
    }
    elseif ($date === $today && $openSession) {
      $state = 'active';
    }
    elseif ($date === $today && $clockedHours <= 0.0) {
      $state = 'today_pending';
    }
    elseif ($date < $today && $clockedHours <= 0.0) {
      $state = 'unclocked';
    }
    elseif ($plannedHours <= 0.0) {
      $state = 'clocked_without_plan';
    }
    else {
      $delta = $clockedHours - $plannedHours;
      $state = abs($delta) <= 0.25 ? 'match' : ($delta < 0 ? 'under' : 'over');
    }

    return $this->result($plannedHours, $clockedHours, $state, $openSession);
  }

  /**
   * @return array{planned_hours:float,clocked_hours:float,delta_hours:float,state:string,open_session:bool}
   */
  private function result(float $plannedHours, float $clockedHours, string $state, bool $openSession): array {
    return [
      'planned_hours' => round($plannedHours, 2),
      'clocked_hours' => round($clockedHours, 2),
      'delta_hours' => round($clockedHours - $plannedHours, 2),
      'state' => $state,
      'open_session' => $openSession,
    ];
  }

}
