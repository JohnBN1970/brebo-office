<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Framework-neutral adapter for POST /api/onsite/v1/presence.
 * The HTTP router must verify the bearer token and supply the employee ID.
 * No unverified employee identifier may be taken from the request payload.
 */
final class OnSitePresenceApiHandler {
  public function __construct(private OnSiteAutomaticIngest $ingest) {}

  /**
   * @param callable(string,string,string,string):bool $authorized
   * @return array{status:int,body:array<string,mixed>}
   */
  public function handle(
    array $payload,
    ?string $verifiedEmployeeId,
    callable $authorized,
    ?DateTimeImmutable $receivedAt = null,
  ): array {
    if ($verifiedEmployeeId === null || $verifiedEmployeeId === '') {
      return ['status' => 401, 'body' => ['ok' => false, 'error' => 'device_not_linked']];
    }
    if (($payload['trigger'] ?? null) !== 'automatic_geofence') {
      return ['status' => 400, 'body' => ['ok' => false, 'error' => 'invalid_trigger']];
    }
    try {
      $result = $this->ingest->accept($payload, $verifiedEmployeeId, $authorized, $receivedAt);
      return [
        'status' => $result['accepted'] ? 201 : 200,
        'body' => ['ok' => true, 'event_id' => $result['event_id'], 'duplicate' => !$result['accepted']],
      ];
    }
    catch (InvalidArgumentException $exception) {
      return ['status' => 400, 'body' => ['ok' => false, 'error' => 'invalid_presence_event']];
    }
  }
}
