<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** In-memory demonstration only. Never writes clock events or approvals. */
final class OfficeInzetWeekDemo {
  public static function sessions(\DateTimeImmutable $monday, array $projectIds): array {
    if (count($projectIds) < 2) return [];
    $ids = array_values(array_map('intval', array_slice($projectIds, 0, 2)));
    $hours = [[8,6.75,8,9,8],[8,8,4,8,6],[6,8,8,8,8]];
    $sessions = [];
    foreach ($hours as $personIndex => $week) {
      foreach ($week as $dayIndex => $total) {
        $first = min($total, [5,4,3,6,4][$dayIndex]);
        foreach ([$first, $total - $first] as $part => $duration) {
          if ($duration <= 0) continue;
          $date = $monday->modify('+' . $dayIndex . ' days')->setTime(8, 0);
          if ($part === 1) $date = $date->modify('+' . (int)round($first * 3600) . ' seconds');
          $end = $date->modify('+' . (int)round($duration * 3600) . ' seconds');
          $sessions[] = ['person_id'=>900001 + $personIndex,'project_id'=>$ids[$part],
            'started_at'=>$date->format('Y-m-d H:i:s'),'ended_at'=>$end->format('Y-m-d H:i:s'),
            'duration_seconds'=>(int)round($duration * 3600),'approval_status'=>'demo'];
        }
      }
    }
    return $sessions;
  }
}
