<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use InvalidArgumentException;
use RuntimeException;

/** Revoke exactly one Office-native device credential with manager authorization. */
final class OnSiteOfficeDeviceRevocationAction {
  public function __construct(private PDO $db) {}

  public function revoke(string $operatorId, int $projectId, int $personId, string $tokenHash): bool {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId < 1 || $personId < 1
      || preg_match('/^[a-f0-9]{64}$/D', $tokenHash) !== 1) {
      throw new InvalidArgumentException('Invalid device revocation context');
    }
    $this->db->beginTransaction();
    try {
      $authorized = $this->db->prepare("SELECT 1 FROM office_inzet_project_team t
        INNER JOIN office_project_memberships m ON m.project_id = t.project_id
        INNER JOIN office_operator_accounts a ON a.id = m.operator_id
        INNER JOIN office_inzet_people p ON p.id = t.person_id
        WHERE t.project_id = ? AND t.person_id = ? AND m.operator_id = ?
          AND m.permission = 'manage' AND a.active = 1 AND p.active = 1 LIMIT 1");
      $authorized->execute([$projectId, $personId, $operatorId]);
      if ($authorized->fetchColumn() === false) throw new RuntimeException('Device revocation not authorized');
      $revoke = $this->db->prepare("UPDATE office_onsite_device_tokens
        SET revoked_at = UTC_TIMESTAMP()
        WHERE token_hash = ? AND person_id = ? AND revoked_at IS NULL");
      $revoke->execute([$tokenHash, $personId]);
      $changed = $revoke->rowCount() === 1;
      $this->db->commit();
      return $changed;
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }
}
