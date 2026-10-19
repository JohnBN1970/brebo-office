<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Device-authenticated automatic event boundary.
 * Does not enroll devices, grant access or approve work hours.
 */
final class OnSiteAuthenticatedEventService {
  public function __construct(
    private OnSiteDeviceTokenRepository $devices,
    private OnSiteAutomaticIngest $ingest,
  ) {}

  /** @return array{status:int,body:array<string,mixed>} */
  public function submit(string $deviceToken, string $eventId, array $event): array {
    $employee = $this->devices->resolveEmployee($deviceToken);
    if ($employee === null) {
      return ['status'=>401,'body'=>['ok'=>false,'error'=>'device_not_linked']];
    }
    try {
      $result = $this->ingest->ingest($employee,$eventId,$event);
    } catch (\InvalidArgumentException) {
      return ['status'=>422,'body'=>['ok'=>false,'error'=>'event_not_authorized']];
    }
    return ['status'=>202,'body'=>[
      'ok'=>true,
      'accepted'=>$result['accepted'],
      'duplicate'=>$result['duplicate'],
    ]];
  }
}
