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
    $projects = [];
    foreach ($zones as $zone) {
      if (!is_array($zone)
        || !isset($zone['project_id'],$zone['zone_id'],$zone['zone_name'],$zone['latitude'],$zone['longitude'],$zone['radius_metres'])
        || !is_string($zone['project_id']) || !is_string($zone['zone_id'])
        || !preg_match('/^[1-9][0-9]{0,18}$/D',$zone['project_id'])
        || !preg_match('/^[1-9][0-9]{0,18}$/D',$zone['zone_id'])
        || !is_string($zone['zone_name']) || trim($zone['zone_name']) === ''
        || !is_numeric($zone['latitude']) || !is_numeric($zone['longitude']) || !is_numeric($zone['radius_metres'])) {
        throw new RuntimeException('Unverified bootstrap assignment');
      }
      $lat = (float)$zone['latitude'];
      $lon = (float)$zone['longitude'];
      $radius = (float)$zone['radius_metres'];
      if (!is_finite($lat) || !is_finite($lon) || !is_finite($radius)
        || abs($lat) > 90 || abs($lon) > 180 || $radius <= 0 || $radius > 1000) {
        throw new RuntimeException('Invalid bootstrap geometry');
      }
      $projectId = $zone['project_id'];
      if (!isset($projects[$projectId])) {
        $projects[$projectId] = ['id'=>$projectId,'name'=>$projectId,'zones'=>[]];
      }
      $projects[$projectId]['zones'][] = [
        'id'=>$zone['zone_id'],
        'name'=>$zone['zone_name'],
        'latitude'=>$lat,
        'longitude'=>$lon,
        'radius_metres'=>$radius,
      ];
    }
    return ['status'=>200,'body'=>[
      'ok'=>true,
      'employee'=>['id'=>$employeeId,'display_name'=>$employeeId,'language'=>'nl'],
      'projects'=>array_values($projects),
    ]];
  }
}
