<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/**
 * Boundary for a trusted server-side Drupal zone export.
 * The caller must resolve the Drupal project ID to a verified Office project ID.
 * No public/mobile endpoint may invoke this adapter.
 */
final class OnSiteCanonicalZoneImport {
  public function __construct(private OnSiteCanonicalZoneSync $sync) {}

  /** @param array<string,mixed> $source */
  public function import(array $source, string $verifiedOfficeProjectId): bool {
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $verifiedOfficeProjectId)) {
      throw new InvalidArgumentException('unverified_office_project');
    }
    foreach (['zone_id', 'source_revision'] as $key) {
      if (!isset($source[$key]) || !is_int($source[$key]) || $source[$key] < 1) {
        throw new InvalidArgumentException('invalid_source_zone');
      }
    }
    if (!isset($source['active']) || !is_bool($source['active'])) {
      throw new InvalidArgumentException('invalid_source_zone');
    }
    return $this->sync->apply([
      'zone_id' => (string) $source['zone_id'],
      'project_id' => $verifiedOfficeProjectId,
      'active' => $source['active'],
      'source_revision' => $source['source_revision'],
      'zone_name' => $source['zone_name'] ?? null,
      'latitude' => $source['latitude'] ?? null,
      'longitude' => $source['longitude'] ?? null,
      'radius_metres' => $source['radius_metres'] ?? null,
    ]);
  }
}
