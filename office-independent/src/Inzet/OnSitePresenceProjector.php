<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Pure read-side projection of ordered, authorized clock events into presence
 * sessions. Does not approve payable hours or modify original clock events.
 */
final class OnSitePresenceProjector {
  /**
   * @param list<array{event_id:string,person_id:int,project_id:int,action:string,occurred_at:string}> $events
   * @return array{sessions:list<array<string,mixed>>,exceptions:list<array<string,mixed>>}
   */
  public function project(array $events): array {
    usort($events, static fn(array $a, array $b): int =>
      (new \DateTimeImmutable((string)$a['occurred_at']))->getTimestamp() <=> (new \DateTimeImmutable((string)$b['occurred_at']))->getTimestamp()
      ?: strcmp((string)$a['event_id'], (string)$b['event_id']));
    $open = [];
    $sessions = [];
    $exceptions = [];
    $seen = [];
    foreach ($events as $event) {
      $id = (string)$event['event_id'];
      if (isset($seen[$id])) continue;
      $seen[$id] = true;
      $person = (int)$event['person_id'];
      $project = (int)$event['project_id'];
      $key = (string)$person;
      $action = (string)$event['action'];
      if ($action === 'in') {
        if (isset($open[$key])) {
          $exceptions[] = ['reason'=>'already_clocked_in','event_id'=>$id,'person_id'=>$person,'project_id'=>$project];
          continue;
        }
        $open[$key] = $event;
      } elseif ($action === 'out') {
        if (!isset($open[$key])) {
          $exceptions[] = ['reason'=>'clock_out_without_in','event_id'=>$id,'person_id'=>$person,'project_id'=>$project];
          continue;
        }
        $start = $open[$key];
        if ((int)$start['project_id'] !== $project) {
          $exceptions[] = ['reason'=>'clock_out_wrong_project','event_id'=>$id,'person_id'=>$person,'project_id'=>$project];
          continue;
        }
        $seconds = (new \DateTimeImmutable($event['occurred_at']))->getTimestamp() -
          (new \DateTimeImmutable($start['occurred_at']))->getTimestamp();
        if ($seconds < 0) {
          $exceptions[] = ['reason'=>'negative_duration','event_id'=>$id,'person_id'=>$person,'project_id'=>$project];
          continue;
        }
        $sessions[] = ['person_id'=>$person,'project_id'=>$project,
          'in_event_id'=>$start['event_id'],'out_event_id'=>$id,
          'started_at'=>$start['occurred_at'],'ended_at'=>$event['occurred_at'],
          'duration_seconds'=>$seconds,'approval_status'=>'pending'];
        unset($open[$key]);
      } else {
        $exceptions[] = ['reason'=>'unknown_action','event_id'=>$id,'person_id'=>$person,'project_id'=>$project];
      }
    }
    foreach ($open as $start) {
      $exceptions[] = ['reason'=>'open_session','event_id'=>$start['event_id'],
        'person_id'=>(int)$start['person_id'],'project_id'=>(int)$start['project_id']];
    }
    return ['sessions'=>$sessions,'exceptions'=>$exceptions];
  }
}
