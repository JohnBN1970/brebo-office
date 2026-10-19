<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Validates device-generated zone transitions without accepting an employee id
 * or raw location history. Does not authorize, persist or approve time entries.
 */
final class OnSiteAutomaticEventValidator {
  /** @return array{project_id:string,zone_id:string,kind:string,occurred_at:string} */
  public function validate(array $event): array {
    foreach (['project_id', 'zone_id', 'kind', 'occurred_at'] as $key) {
      if (!isset($event[$key]) || !is_string($event[$key])) {
        throw new InvalidArgumentException('invalid_' . $key);
      }
    }
    foreach (['employee_id', 'person_id', 'latitude', 'longitude', 'coordinates'] as $forbidden) {
      if (array_key_exists($forbidden, $event)) {
        throw new InvalidArgumentException('forbidden_' . $forbidden);
      }
    }
    $project = trim($event['project_id']);
    $zone = trim($event['zone_id']);
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $project)
      || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $zone)) {
      throw new InvalidArgumentException('invalid_zone_reference');
    }
    if (!in_array($event['kind'], ['in', 'out'], true)) {
      throw new InvalidArgumentException('invalid_transition');
    }
    $timestamp = $event['occurred_at'];
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d{1,6})?(?:Z|[+-]\\d{2}:\\d{2})$/D', $timestamp)) {
      throw new InvalidArgumentException('invalid_timestamp');
    }
    try {
      $date = new DateTimeImmutable($timestamp);
    } catch (\Exception) {
      throw new InvalidArgumentException('invalid_timestamp');
    }
    $utc = $date->setTimezone(new DateTimeZone('UTC'));
    return [
      'project_id' => $project,
      'zone_id' => $zone,
      'kind' => $event['kind'],
      'occurred_at' => $utc->format('Y-m-d\\TH:i:s.u\\Z'),
    ];
  }
}
