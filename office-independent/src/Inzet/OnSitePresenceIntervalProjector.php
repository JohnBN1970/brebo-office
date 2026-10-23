<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Read-only pairing of authenticated OnSite events.
 * Produces evidence intervals, never approved or payable hours.
 */
final class OnSitePresenceIntervalProjector {
  public function project(array $events): array {
    $ordered = [];
    foreach ($events as $event) {
      foreach (['event_id','person_id','project_id','zone_id','event_kind','occurred_at_utc'] as $key) {
        if (!isset($event[$key]) || !is_scalar($event[$key])) {
          throw new InvalidArgumentException('invalid_presence_event');
        }
      }
      if (!in_array($event['event_kind'], ['in','out'], true)) {
        throw new InvalidArgumentException('invalid_presence_transition');
      }
      $event['timestamp'] = (new DateTimeImmutable((string)$event['occurred_at_utc']))->getTimestamp();
      $ordered[] = $event;
    }
    usort($ordered, static fn(array $a,array $b):int =>
      ($a['timestamp'] <=> $b['timestamp']) ?: strcmp((string)$a['event_id'],(string)$b['event_id']));
    $open = [];
    $intervals = [];
    $exceptions = [];
    foreach ($ordered as $event) {
      $key = (string)$event['person_id'].'|'.(string)$event['project_id'];
      if ($event['event_kind'] === 'in') {
        if (isset($open[$key])) {
          $exceptions[] = ['reason'=>'overlapping_arrival','event_id'=>(string)$event['event_id'],
            'open_event_id'=>(string)$open[$key]['event_id']];
        } else {
          $open[$key] = $event;
        }
        continue;
      }
      if (!isset($open[$key])) {
        $exceptions[] = ['reason'=>'departure_without_arrival','event_id'=>(string)$event['event_id']];
        continue;
      }
      $arrival = $open[$key];
      if ((string)$arrival['zone_id'] !== (string)$event['zone_id']) {
        $exceptions[] = ['reason'=>'zone_mismatch','event_id'=>(string)$event['event_id'],
          'open_event_id'=>(string)$arrival['event_id']];
        continue;
      }
      if ($event['timestamp'] <= $arrival['timestamp']) {
        $exceptions[] = ['reason'=>'nonpositive_interval','event_id'=>(string)$event['event_id']];
        continue;
      }
      $intervals[] = ['person_id'=>(string)$event['person_id'],
        'project_id'=>(string)$event['project_id'],'zone_id'=>(string)$event['zone_id'],
        'arrival_event_id'=>(string)$arrival['event_id'],
        'departure_event_id'=>(string)$event['event_id'],
        'entered_at'=>(string)$arrival['occurred_at_utc'],
        'left_at'=>(string)$event['occurred_at_utc'],
        'elapsed_seconds'=>$event['timestamp']-$arrival['timestamp'],
        'status'=>'evidence_only'];
      unset($open[$key]);
    }
    $active = [];
    foreach ($open as $arrival) {
      $active[] = [
        'person_id'=>(string)$arrival['person_id'],
        'project_id'=>(string)$arrival['project_id'],
        'zone_id'=>(string)$arrival['zone_id'],
        'arrival_event_id'=>(string)$arrival['event_id'],
        'entered_at'=>(string)$arrival['occurred_at_utc'],
        'status'=>'presence_evidence_open',
      ];
    }
    return ['intervals'=>$intervals,'active'=>$active,'exceptions'=>$exceptions];
  }
}
