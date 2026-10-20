<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Explicit, reviewed Drupal-to-Office project identity mapping.
 * No numeric-ID equality fallback. Never accept project mapping from a device.
 */
final class OnSiteVerifiedProjectMapping {
  public function __construct(private PDO $db) {}

  /** @return \Closure(string):(?string) */
  public function resolver(): \Closure {
    return function (string $sourceProjectId): ?string {
      if (!preg_match('/^[1-9][0-9]{0,18}$/D', $sourceProjectId)) return null;
      $query = $this->db->prepare(
        'SELECT office_project_id FROM office_onsite_project_mapping
         WHERE source_project_id = ? AND verified = 1 LIMIT 1'
      );
      $query->execute([$sourceProjectId]);
      $officeProjectId = $query->fetchColumn();
      return is_string($officeProjectId) && preg_match('/^[1-9][0-9]{0,18}$/D', $officeProjectId)
        ? $officeProjectId : null;
    };
  }

  /** Review-only schema; do not apply to production automatically. */
  public static function schema(): string {
    return 'CREATE TABLE office_onsite_project_mapping (
      source_project_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      office_project_id BIGINT UNSIGNED NOT NULL,
      verified TINYINT(1) NOT NULL DEFAULT 0,
      UNIQUE KEY idx_onsite_verified_office_project (office_project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
  }
}
