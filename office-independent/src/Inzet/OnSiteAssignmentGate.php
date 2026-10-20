<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/** Validates an event against the server-resolved employee assignment. */
final class OnSiteAssignmentGate {
  public function __construct(private OnSiteAssignmentRepository $assignments) {}

  public function authorize(string $verifiedEmployeeId, array $event): bool {
    if ($verifiedEmployeeId === '' || strlen($verifiedEmployeeId) > 128) {
      return false;
    }
    foreach (['project_id', 'zone_id'] as $key) {
      if (!isset($event[$key]) || !is_string($event[$key]) || $event[$key] === '') {
        return false;
      }
    }
    foreach (['employee_id', 'person_id', 'uid'] as $key) {
      if (array_key_exists($key, $event)) {
        throw new InvalidArgumentException('Client-supplied employee identity forbidden');
      }
    }
    return $this->assignments->isAssigned(
      $verifiedEmployeeId,
      $event['project_id'],
      $event['zone_id']
    );
  }
}
