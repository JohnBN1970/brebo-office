<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use InvalidArgumentException;

/**
 * Only server-fetched Office projects may seed a personnel-zone map.
 * No browser-supplied project coordinates or Drupal identifiers are trusted.
 */
final class OnSiteOfficeProjectMapSeed {
  /**
   * @param array<string,mixed> $project Server-fetched authorized Office project.
   * @return array{project_id:string,latitude:float,longitude:float}
   */
  public static function fromAuthorizedProject(array $project): array {
    $id=$project['id'] ?? null;
    $latitude=$project['latitude'] ?? null;
    $longitude=$project['longitude'] ?? null;
    if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]{0,18}$/D',(string)$id)
      || !is_numeric($latitude) || !is_numeric($longitude)
      || !is_finite((float)$latitude) || !is_finite((float)$longitude)
      || abs((float)$latitude)>90 || abs((float)$longitude)>180) {
      throw new InvalidArgumentException('office_project_map_seed_unavailable');
    }
    return ['project_id'=>(string)$id,'latitude'=>(float)$latitude,'longitude'=>(float)$longitude];
  }
}
