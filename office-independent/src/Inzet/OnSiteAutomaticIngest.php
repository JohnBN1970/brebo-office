<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Application service: authenticated employee identity must come from the
 * verified device token, never from the mobile request body.
 */
final class OnSiteAutomaticIngest {
  public function __construct(
    private OnSiteEventInbox $inbox,
  ) {}

  /**
   * @param callable(string,string,string):bool $authorized
   *   Checks employee/project/zone assignment at the event time.
   * @return array{accepted:bool,event_id:string}
   */
  public function accept(
    array $payload,
    string $verifiedEmployeeId,
    callable $authorized,
    ?DateTimeImmutable $receivedAt = null,
  ): array {
    if ($verifiedEmployeeId === '') {
      throw new InvalidArgumentException('Device identity required');
    }
    $event = OnSiteAutomaticEventValidator::validate(
      $payload,
      $receivedAt ?? new DateTimeImmutable('now'),
    );
    if (!$authorized($verifiedEmployeeId, $event['project_id'], $event['zone_id'])) {
      throw new InvalidArgumentException('Project zone not assigned');
    }
    $event['employee_id'] = $verifiedEmployeeId;
    return [
      'accepted' => $this->inbox->receive($event),
      'event_id' => $event['event_id'],
    ];
  }
}
