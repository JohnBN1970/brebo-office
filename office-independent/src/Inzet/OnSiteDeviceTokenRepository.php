<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;

/**
 * Read-only device credential lookup. Never creates a second personnel master.
 * Enrollment and canonical identity mapping must be verified separately.
 */
final class OnSiteDeviceTokenRepository {
  public function __construct(private PDO $db) {}

  public function resolveEmployee(string $token): ?string {
    if (!preg_match('/^[A-Za-z0-9._~+\/-]{32,512}$/D', $token)) return null;
    $query = $this->db->prepare(
      'SELECT d.person_id FROM office_onsite_device_tokens d
       INNER JOIN office_inzet_people p ON p.id = d.person_id
       WHERE d.token_hash = ? AND d.revoked_at IS NULL
         AND (d.expires_at IS NULL OR d.expires_at > UTC_TIMESTAMP())
         AND p.active = 1 LIMIT 1'
    );
    $query->execute([hash('sha256', $token)]);
    $id = $query->fetchColumn();
    return $id === false ? null : (string)$id;
  }
}
