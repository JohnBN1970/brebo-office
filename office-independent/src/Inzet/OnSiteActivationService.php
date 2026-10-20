<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use Throwable;

/** Atomic Office-owned single-use device activation; no Drupal runtime. */
final class OnSiteActivationService {
  public function __construct(
    private PDO $db,
    private OnSiteDeviceTokenRepository $devices,
  ) {}

  /** @return array{status:int,body:array<string,mixed>} */
  public function activate(string $activationToken): array {
    if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $activationToken)) {
      return ['status'=>400,'body'=>['ok'=>false,'error'=>'invalid_activation_token']];
    }
    try {
      $this->db->beginTransaction();
      $q = $this->db->prepare('SELECT i.person_id FROM office_onsite_activation_invitations i
        INNER JOIN office_inzet_people p ON p.id = i.person_id AND p.active = 1
        WHERE i.token_hash = ? AND i.consumed_at IS NULL AND i.expires_at > UTC_TIMESTAMP() FOR UPDATE');
      $q->execute([hash('sha256', $activationToken)]);
      $personId = $q->fetchColumn();
      if ($personId === false) {
        $this->db->rollBack();
        return ['status'=>401,'body'=>['ok'=>false,'error'=>'invalid_or_expired_activation']];
      }
      $deviceToken = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
      $insert = $this->db->prepare('INSERT INTO office_onsite_device_tokens (token_hash, person_id) VALUES (?, ?)');
      $insert->execute([hash('sha256', $deviceToken), $personId]);
      $consume = $this->db->prepare('UPDATE office_onsite_activation_invitations SET consumed_at = UTC_TIMESTAMP()
        WHERE token_hash = ? AND consumed_at IS NULL');
      $consume->execute([hash('sha256', $activationToken)]);
      if ($consume->rowCount() !== 1) {
        throw new \RuntimeException('Invitation was already consumed');
      }
      $this->db->commit();
      return ['status'=>200,'body'=>['ok'=>true,'device_token'=>$deviceToken]];
    } catch (Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }
}
