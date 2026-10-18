<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Office-native bootstrap: verified device identity only, with an authoritative
 * Office assignment/zone provider. Never derives identity from request input.
 */
final class OnSiteBootstrapService {
  public function __construct(private OnSiteDeviceTokenRepository $devices) {}

  /**
   * @param callable(string):array{employee:array<string,mixed>,projects:array<int,array<string,mixed>>} $provider
   * @return array{status:int,body:array<string,mixed>}
   */
  public function bootstrap(?string $bearerToken, callable $provider): array {
    $employeeId = $bearerToken === null ? null : $this->devices->resolve($bearerToken);
    if ($employeeId === null) {
      return ['status'=>401,'body'=>['ok'=>false,'error'=>'device_not_linked']];
    }
    try {
      $data = $provider($employeeId);
      if (!is_array($data) || !isset($data['employee'], $data['projects'])
        || !is_array($data['employee']) || !is_array($data['projects'])
        || ($data['employee']['id'] ?? null) !== $employeeId) {
        throw new InvalidArgumentException('Invalid Office bootstrap identity');
      }
      $employee = $data['employee'];
      if (!is_string($employee['display_name'] ?? null) || $employee['display_name'] === ''
        || !is_string($employee['language'] ?? null) || $employee['language'] === '') {
        throw new InvalidArgumentException('Incomplete employee');
      }
      $projects = [];
      foreach ($data['projects'] as $project) {
        if (!is_array($project) || !preg_match('/^[1-9][0-9]*$/D', (string)($project['id'] ?? ''))
          || !is_string($project['name'] ?? null) || $project['name'] === ''
          || !is_array($project['zones'] ?? null)) throw new InvalidArgumentException('Invalid project');
        $zones = [];
        foreach ($project['zones'] as $zone) {
          if (!is_array($zone) || !preg_match('/^[1-9][0-9]*$/D', (string)($zone['id'] ?? ''))
            || !is_string($zone['name'] ?? null) || $zone['name'] === ''
            || !is_numeric($zone['latitude'] ?? null) || !is_numeric($zone['longitude'] ?? null)
            || !is_numeric($zone['radius_metres'] ?? null)
            || !is_finite((float)$zone['latitude']) || !is_finite((float)$zone['longitude'])
            || !is_finite((float)$zone['radius_metres'])
            || abs((float)$zone['latitude']) > 90 || abs((float)$zone['longitude']) > 180
            || (float)$zone['radius_metres'] < 25 || (float)$zone['radius_metres'] > 1000
            || ($zone['active'] ?? null) !== true) throw new InvalidArgumentException('Invalid active zone');
          $zones[] = [
            'id'=>(string)$zone['id'],'name'=>$zone['name'],
            'latitude'=>(float)$zone['latitude'],'longitude'=>(float)$zone['longitude'],
            'radius_metres'=>(float)$zone['radius_metres'],
          ];
        }
        if ($zones !== []) $projects[] = ['id'=>(string)$project['id'],'name'=>$project['name'],'zones'=>$zones];
      }
      return ['status'=>200,'body'=>[
        'employee'=>['id'=>$employeeId,'display_name'=>$employee['display_name'],'language'=>$employee['language']],
        'projects'=>$projects,
      ]];
    } catch (\Throwable) {
      return ['status'=>503,'body'=>['ok'=>false,'error'=>'bootstrap_unavailable']];
    }
  }
}
