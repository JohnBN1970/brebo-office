<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** HTTP-independent presence handler; authentication and authorization remain mandatory. */
final class OnSitePresenceApiHandler {
  public function __construct(
    private OnSitePresenceRequestService $requests,
    private OnSiteAuthenticatedEventService $events,
  ) {}

  /** @return array{status:int,body:array<string,mixed>} */
  public function submit(array $payload, string $deviceToken): array {
    try {
      $request = $this->requests->parse($payload);
    } catch (\InvalidArgumentException) {
      return ['status'=>400,'body'=>['ok'=>false,'error'=>'invalid_event_request']];
    }
    return $this->events->submit($deviceToken, $request['event_id'], $request['event']);
  }
}
