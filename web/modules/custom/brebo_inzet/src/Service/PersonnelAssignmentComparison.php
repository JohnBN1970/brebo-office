<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\NodeInterface;

/**
 * Compares planned personnel assignments with actual clock registrations.
 */
final class PersonnelAssignmentComparison {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
   * @return array{planned_hours: float, clocked_hours: float, delta_hours: float, state: string, open_session: bool}
   */
  public function compare(NodeInterface $assignment): array {
    $date = (string) ($assignment->get('field_brebo_plan_date')->value ?? '');
    $projectId = (int) ($assignment->get('field_brebo_project_ref')->target_id ?? 0);
    $userId = (int) ($assignment->get('field_brebo_plan_user')->target_id ?? 0);
    $plannedHours = $this->plannedHours($assignment);

    if ($date === '' || $projectId <= 0 || $userId <= 0) {
      return $this->result($plannedHours, 0.0, 'incomplete', FALSE);
    }

    $timezoneName = (string) ($this->configFactory->get('system.date')->get('timezone.default') ?: date_default_timezone_get());
    $timezone = new \DateTimeZone($timezoneName ?: 'Europe/Brussels');
    $utc = new \DateTimeZone('UTC');
    $dayStartLocal = new \DateTimeImmutable($date . ' 00:00:00', $timezone);
    $dayEndLocal = $dayStartLocal->modify('+1 day');
    $dayStartUtc = $dayStartLocal->setTimezone($utc);
    $dayEndUtc = $dayEndLocal->setTimezone($utc);

    [$windowStartUtc, $windowEndUtc] = $this->assignmentWindow($assignment, $dayStartLocal, $dayEndLocal, $utc);

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'brebo_clock_registration')
      ->condition('field_brebo_project_ref', $projectId)
      ->condition('field_brebo_clock_user', $userId)
      ->condition('field_brebo_clock_in', $dayEndUtc->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<')
      ->execute();

    $seconds = 0;
    $openSession = FALSE;
    $now = (new \DateTimeImmutable('@' . $this->time->getCurrentTime()))->setTimezone($utc);

    foreach ($storage->loadMultiple($ids) as $clock) {
      if (!$clock instanceof NodeInterface || !$clock->access('view')) {
        continue;
      }
      $inValue = (string) ($clock->get('field_brebo_clock_in')->value ?? '');
      if ($inValue === '') {
        continue;
      }
      $in = new \DateTimeImmutable($inValue, $utc);
      $outValue = (string) ($clock->get('field_brebo_clock_out')->value ?? '');
      if ($outValue === '') {
        $out = $now < $dayEndUtc ? $now : $dayEndUtc;
        $openSession = $out > $windowStartUtc && $in < $windowEndUtc;
      }
      else {
        $out = new \DateTimeImmutable($outValue, $utc);
      }

      // Include sessions that started before midnight but overlap this day,
      // then allocate only the part inside this assignment's time window.
      if ($out <= $dayStartUtc || $in >= $dayEndUtc) {
        continue;
      }
      $from = $in > $windowStartUtc ? $in : $windowStartUtc;
      $to = $out < $windowEndUtc ? $out : $windowEndUtc;
      if ($to > $from) {
        $seconds += $to->getTimestamp() - $from->getTimestamp();
      }
    }

    $clockedHours = round($seconds / 3600, 2);
    $today = (new \DateTimeImmutable('@' . $this->time->getCurrentTime()))->setTimezone($timezone)->format('Y-m-d');

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
   * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
   */
  private function assignmentWindow(NodeInterface $assignment, \DateTimeImmutable $dayStartLocal, \DateTimeImmutable $dayEndLocal, \DateTimeZone $utc): array {
    $start = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '');
    $end = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '');
    if (preg_match('/^\d{2}:\d{2}$/', $start) !== 1 || preg_match('/^\d{2}:\d{2}$/', $end) !== 1) {
      return [$dayStartLocal->setTimezone($utc), $dayEndLocal->setTimezone($utc)];
    }

    $windowStart = new \DateTimeImmutable($dayStartLocal->format('Y-m-d') . ' ' . $start . ':00', $dayStartLocal->getTimezone());
    $windowEnd = new \DateTimeImmutable($dayStartLocal->format('Y-m-d') . ' ' . $end . ':00', $dayStartLocal->getTimezone());
    if ($windowEnd <= $windowStart) {
      return [$dayStartLocal->setTimezone($utc), $dayEndLocal->setTimezone($utc)];
    }
    return [$windowStart->setTimezone($utc), $windowEnd->setTimezone($utc)];
  }

  private function plannedHours(NodeInterface $assignment): float {
    $explicit = (float) ($assignment->get('field_brebo_planned_hours')->value ?? 0);
    if ($explicit > 0) {
      return round($explicit, 2);
    }

    $start = (string) ($assignment->get('field_brebo_assignment_start')->value ?? '');
    $end = (string) ($assignment->get('field_brebo_assignment_end')->value ?? '');
    if (preg_match('/^(\d{2}):(\d{2})$/', $start, $startParts) !== 1 || preg_match('/^(\d{2}):(\d{2})$/', $end, $endParts) !== 1) {
      return 0.0;
    }
    $startMinutes = ((int) $startParts[1] * 60) + (int) $startParts[2];
    $endMinutes = ((int) $endParts[1] * 60) + (int) $endParts[2];
    if ($endMinutes <= $startMinutes) {
      return 0.0;
    }
    return round(($endMinutes - $startMinutes) / 60, 2);
  }

  /**
   * @return array{planned_hours: float, clocked_hours: float, delta_hours: float, state: string, open_session: bool}
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
