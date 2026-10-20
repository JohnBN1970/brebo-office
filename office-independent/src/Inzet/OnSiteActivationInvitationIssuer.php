<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Issue an Office-owned invitation only after the caller has authorized the
 * operator and verified the intended employee. Never log or persist plaintext.
 */
final class OnSiteActivationInvitationIssuer {
  public function __construct(private PDO $db) {}

  /** @return array{activation_token:string,expires_at:string} */
  public function issue(int $personId, int $lifetimeMinutes = 15): array {
    if ($personId <= 0 || $lifetimeMinutes < 1 || $lifetimeMinutes > 60) {
      throw new RuntimeException('Invalid activation invitation parameters');
    }
    $query = $this->db->prepare('SELECT id FROM office_inzet_people WHERE id = ? AND active = 1 LIMIT 1');
    $query->execute([$personId]);
    if ($query->fetchColumn() === false) throw new RuntimeException('Active Office employee not found');

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $expiry = gmdate('Y-m-d H:i:s', time() + $lifetimeMinutes * 60);
    $insert = $this->db->prepare(
      'INSERT INTO office_onsite_activation_invitations (token_hash, person_id, expires_at) VALUES (?, ?, ?)'
    );
    $insert->execute([hash('sha256', $token), $personId, $expiry]);
    return ['activation_token' => $token, 'expires_at' => $expiry];
  }
}
