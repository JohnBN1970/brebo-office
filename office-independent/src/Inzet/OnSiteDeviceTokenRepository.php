<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;

/** Resolves Office device identities from hashed bearer credentials. */
final class OnSiteDeviceTokenRepository {
  public function __construct(private PDO $db) {}

  public function resolve(string $token): ?string {
    if (strlen($token) < 32) return null;
    $query = $this->db->prepare('SELECT employee_id FROM onsite_device_tokens WHERE token_hash = ? AND revoked_at IS NULL');
    $query->execute([hash('sha256', $token)]);
    $employee = $query->fetchColumn();
    return is_string($employee) && $employee !== '' ? $employee : null;
  }
}
