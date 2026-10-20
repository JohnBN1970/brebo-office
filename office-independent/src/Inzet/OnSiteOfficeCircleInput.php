<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/**
 * Validate Office map-circle inputs before they reach a trusted zone writer.
 * Project authorization and database identity remain server-side responsibilities.
 */
final class OnSiteOfficeCircleInput {
  /** @param array<string,mixed> $input
   * @return array{project_id:string,active:bool,zone_name:string,latitude:float,longitude:float,radius_metres:float}
   */
  public static function forCreate(array $input): array {
    foreach (['zone_id','source_revision','expected_revision'] as $key) {
      if (array_key_exists($key, $input)) throw new InvalidArgumentException('office_circle_client_identity');
    }
    $project = $input['project_id'] ?? null;
    $name = $input['zone_name'] ?? null;
    $lat = $input['latitude'] ?? null;
    $lon = $input['longitude'] ?? null;
    $radius = $input['radius_metres'] ?? null;
    if (!is_string($project) || !preg_match('/^[1-9][0-9]{0,18}$/D', $project)
      || !is_string($name) || trim($name) === '' || strlen(trim($name)) > 160
      || !is_numeric($lat) || !is_numeric($lon) || !is_numeric($radius)
      || !is_finite((float)$lat) || !is_finite((float)$lon) || !is_finite((float)$radius)
      || abs((float)$lat) > 90 || abs((float)$lon) > 180
      || (float)$radius <= 0 || (float)$radius > 5000) {
      throw new InvalidArgumentException('invalid_office_circle');
    }
    return ['project_id'=>$project,'active'=>true,'zone_name'=>trim($name),
      'latitude'=>(float)$lat,'longitude'=>(float)$lon,'radius_metres'=>(float)$radius];
  }
}
