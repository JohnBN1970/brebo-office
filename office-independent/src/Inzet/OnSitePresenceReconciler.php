<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Pure reconciler for already authenticated and authorized OnSite evidence.
 * Never treats device presence as approved payroll time.
 */
final class OnSitePresenceReconciler {
  /**
   * @param list<array<string,mixed>> $events Sorted or unsorted device evidence.
   * @return array{sessions:list<array<string,mixed>>,exceptions:list<array<string,string>>}
   */
  public static function reconcile(array $events): array {
    usort($events, static fn(array $a, array $b): int =>
      (strtotime((string)($a['occurred_at'] ?? '')) ?: 0) <=> (strtotime((string)($b['occurred_at'] ?? '')) ?: 0));
    $seen = [];
    $open = [];
    $sessions = [];
    $exceptions = [];
    foreach ($events as $event) {
      $id = trim((string)($event['event_id'] ?? ''));
      $employee = trim((string)($event['employee_id'] ?? ''));
      $project = trim((string)($event['project_id'] ?? ''));
      $zone = trim((string)($event['zone_id'] ?? ''));
      $kind = (string)($event['kind'] ?? '');
      $at = (string)($event['occurred_at'] ?? '');
      if ($id === '' || $employee === '' || $project === '' || $zone === ''
        || !in_array($kind, ['in', 'out'], true) || !self::validTime($at)) {
        throw new InvalidArgumentException('Invalid presence evidence');
      }
      if (isset($seen[$id])) continue; // Retry delivery is idempotent.
      $seen[$id] = true;
      $key = $employee . "\0" . $project;
      if ($kind === 'in') {
        if (isset($open[$key])) {
          $exceptions[] = ['event_id'=>$id, 'reason'=>'duplicate_entry'];
          continue;
        }
        $open[$key] = $event;
        continue;
      }
      if (!isset($open[$key])) {
        $exceptions[] = ['event_id'=>$id, 'reason'=>'exit_without_entry'];
        continue;
      }
      $entry = $open[$key];
      unset($open[$key]);
      $seconds = (new DateTimeImmutable($at))->getTimestamp()
        - (new DateTimeImmutable((string)$entry['occurred_at']))->getTimestamp();
      if ($seconds <= 0 || $seconds > 86400) {
        $exceptions[] = ['event_id'=>$id, 'reason'=>'invalid_session_duration'];
        continue;
      }
      $sessions[] = [
        'employee_id'=>$employee, 'project_id'=>$project,
        'entered_at'=>$entry['occurred_at'], 'left_at'=>$at,
        'duration_seconds'=>$seconds,
        'entry_event_id'=>$entry['event_id'], 'exit_event_id'=>$id,
        'state'=>'pending_review',
      ];
    }
    foreach ($open as $entry) {
      $exceptions[] = ['event_id'=>(string)$entry['event_id'], 'reason'=>'missing_exit'];
    }
    return ['sessions'=>$sessions, 'exceptions'=>$exceptions];
  }

  private static function validTime(string $value): bool {
    if ($value === '') return false;
    try { new DateTimeImmutable($value); return true; }
    catch (\Throwable) { return false; }
  }
}
