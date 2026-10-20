<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use InvalidArgumentException;

/**
 * Boundary for a trusted server-side Drupal zone export.
 * The injected trusted resolver must verify the Drupal-to-Office project mapping.
 * No public/mobile endpoint may invoke this adapter.
 */
final class OnSiteCanonicalZoneImport {
  /** @param callable(string):(?string) $resolveVerifiedProjectId */
  public function __construct(private OnSiteCanonicalZoneSync $sync, private mixed $resolveVerifiedProjectId) {
    if (!is_callable($resolveVerifiedProjectId)) {
      throw new InvalidArgumentException('missing_project_mapping_resolver');
    }
  }

  /** @param array<string,mixed> $source */
  public function import(array $source): bool {
    $sourceProjectId = $source['source_project_id'] ?? null;
    if (!is_int($sourceProjectId) || $sourceProjectId < 1) {
      throw new InvalidArgumentException('invalid_source_project');
    }
    $verifiedOfficeProjectId = ($this->resolveVerifiedProjectId)((string) $sourceProjectId);
    if (!is_string($verifiedOfficeProjectId) || !preg_match('/^[1-9][0-9]{0,18}$/D', $verifiedOfficeProjectId)) {
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
