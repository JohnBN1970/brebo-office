<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;
use Throwable;

/** Issue only for an authorized, verified existing employee; never provision people. */
final class OnSiteActivationInvitationIssuer {
  public function __construct(private PDO $db) {}

  /** @return array{activation_token:string,expires_at:string} */
  public function issue(int $personId, int $lifetimeMinutes = 15, ?int $projectId = null, ?\Closure $authorize = null): array {
    if ($personId <= 0 || $lifetimeMinutes < 1 || $lifetimeMinutes > 60) {
      throw new RuntimeException('Invalid activation invitation parameters');
    }
    $this->db->beginTransaction();
    try {
      // Serialize reissue and redemption on the existing employee row.
      $query = $this->db->prepare('SELECT id FROM office_inzet_people WHERE id = ? AND active = 1 FOR UPDATE');
      $query->execute([$personId]);
      if ($query->fetchColumn() === false) throw new RuntimeException('Active Office employee not found');
      $team = $this->db->prepare('SELECT t.project_id FROM office_inzet_project_team t
        INNER JOIN office_projects p ON p.id = t.project_id WHERE t.person_id = ?');
      $team->execute([$personId]);
      $projects = array_map('intval', $team->fetchAll(PDO::FETCH_COLUMN));
      if ($projectId === null && count($projects) === 1) $projectId = $projects[0];
      if ($projectId === null || !in_array($projectId, $projects, true)) {
        throw new RuntimeException('Active Office project assignment required');
      }
      if ($authorize !== null) $authorize();
      // A new manually confirmed request supersedes all outstanding codes.
      // Existing devices stay individually revocable; reissue grants no extra identity.
      $cancel = $this->db->prepare('UPDATE office_onsite_activation_invitations
        SET consumed_at = UTC_TIMESTAMP() WHERE person_id = ? AND consumed_at IS NULL');
      $cancel->execute([$personId]);
      $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
      $expiry = gmdate('Y-m-d H:i:s', time() + $lifetimeMinutes * 60);
      $insert = $this->db->prepare('INSERT INTO office_onsite_activation_invitations
        (token_hash, person_id, project_id, expires_at) VALUES (?, ?, ?, ?)');
      $insert->execute([hash('sha256', $token), $personId, $projectId, $expiry]);
      $this->db->commit();
      return ['activation_token' => $token, 'expires_at' => $expiry];
    } catch (Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }
}
