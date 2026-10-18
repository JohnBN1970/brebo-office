<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;

/** Resolves Office device identities from hashed bearer credentials. */
final class OnSiteDeviceTokenRepository {
  public function __construct(private PDO $db) {}


  public function migrate(): void {
    $this->db->exec('CREATE TABLE IF NOT EXISTS onsite_device_tokens (
      token_hash TEXT PRIMARY KEY,
      employee_id TEXT NOT NULL,
      revoked_at TEXT NULL
    )');
  }


  public function register(string $token, string $employeeId): void {
    if (strlen($token) < 32 || strlen($token) > 512 || trim($employeeId) === '') {
      throw new \InvalidArgumentException('Invalid device registration');
    }
    $query = $this->db->prepare('INSERT INTO onsite_device_tokens (token_hash, employee_id, revoked_at)
      VALUES (?, ?, NULL)');
    $query->execute([hash('sha256', $token), $employeeId]);
  }

  public function revoke(string $token): void {
    $query = $this->db->prepare('UPDATE onsite_device_tokens SET revoked_at = ?
      WHERE token_hash = ? AND revoked_at IS NULL');
    $query->execute([gmdate('c'), hash('sha256', $token)]);
  }

  public function resolve(string $token): ?string {
    if (strlen($token) < 32 || strlen($token) > 512) return null;
    $query = $this->db->prepare('SELECT employee_id FROM onsite_device_tokens WHERE token_hash = ? AND revoked_at IS NULL');
    $query->execute([hash('sha256', $token)]);
    $employee = $query->fetchColumn();
    return is_string($employee) && $employee !== '' ? $employee : null;
  }
}
