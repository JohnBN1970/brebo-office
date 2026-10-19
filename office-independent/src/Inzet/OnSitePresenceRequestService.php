<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/**
 * Strict transport contract for automatic OnSite device events.
 * Does not authenticate devices or resolve project-zone membership.
 */
final class OnSitePresenceRequestService {
  /** @return array{event_id:string,event:array<string,mixed>} */
  public function parse(array $payload): array {
    foreach (array_keys($payload) as $key) {
      if (!in_array($key, ['event_id','event'], true)) {
        throw new InvalidArgumentException('unexpected_request_field');
      }
    }
    $id = $payload['event_id'] ?? null;
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $id)) {
      throw new InvalidArgumentException('invalid_event_id');
    }
    $event = $payload['event'] ?? null;
    if (!is_array($event) || array_is_list($event)) {
      throw new InvalidArgumentException('invalid_event');
    }
    foreach (['employee_id','person_id','uid','latitude','longitude','coordinates'] as $field) {
      if (array_key_exists($field,$event)) {
        throw new InvalidArgumentException('forbidden_event_field');
      }
    }
    return ['event_id'=>$id,'event'=>$event];
  }
}
