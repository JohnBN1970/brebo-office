<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use RuntimeException;

/** Authenticated, read-only OnSite project/zone bootstrap. */
final class OnSiteBootstrapService {
  public function __construct(private OnSiteDeviceTokenRepository $devices) {}

  /** @param \Closure(string):array $assignments */
  public function bootstrap(string $deviceToken, \Closure $assignments): array {
    $employeeId = $this->devices->resolveEmployee($deviceToken);
    if ($employeeId === null) {
      return ['status'=>401,'body'=>['ok'=>false,'error'=>'device_not_linked']];
    }
    $zones = $assignments($employeeId);
    if (!is_array($zones)) throw new RuntimeException('Invalid bootstrap assignments');
    foreach ($zones as $zone) {
      if (!is_array($zone)
        || !isset($zone['project_id'],$zone['zone_id'])
        || !is_string($zone['project_id']) || !is_string($zone['zone_id'])
        || !preg_match('/^[1-9][0-9]{0,18}$/D',$zone['project_id'])
        || !preg_match('/^[1-9][0-9]{0,18}$/D',$zone['zone_id'])) {
        throw new RuntimeException('Unverified bootstrap assignment');
      }
    }
    return ['status'=>200,'body'=>['ok'=>true,'assignments'=>$zones]];
  }
}
