<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/**
 * Accepts an authenticated device transition only after validation and
 * server-side project-zone authorization. No hours are approved here.
 */
final class OnSiteAutomaticIngest {
  public function __construct(
    private OnSiteAutomaticEventValidator $validator,
    private OnSiteAssignmentGate $assignments,
    private OnSiteEventInbox $inbox,
  ) {}

  /** @return array{accepted:bool,duplicate:bool} */
  public function ingest(string $verifiedEmployeeId, string $eventId, array $payload): array {
    $event = $this->validator->validate($payload);
    if (!$this->assignments->authorize($verifiedEmployeeId, $event)) {
      throw new InvalidArgumentException('project_zone_not_assigned');
    }
    $stored = $this->inbox->store($verifiedEmployeeId, $eventId, $event);
    return ['accepted'=>true, 'duplicate'=>!$stored];
  }
}
