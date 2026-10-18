<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Redeems only an Office-operator-provisioned, single-use activation secret.
 * Provisioning and delivery are intentionally outside public mobile endpoints.
 */
final class OnSiteActivationService {
  public function __construct(private PDO $db, private OnSiteDeviceTokenRepository $devices) {}

  /** @return array{status:int,body:array<string,mixed>} */
  public function activate(string $secret): array {
    if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $secret)) {
      return ['status'=>400,'body'=>['error'=>'invalid_activation_token']];
    }
    $hash = hash('sha256', $secret);
    try {
      $this->db->beginTransaction();
      $q = $this->db->prepare('SELECT employee_id, expires_at, consumed_at FROM onsite_activation_tokens WHERE token_hash = ? FOR UPDATE');
      $q->execute([$hash]);
      $row = $q->fetch(PDO::FETCH_ASSOC);
      if (!is_array($row) || $row['consumed_at'] !== null
        || new \DateTimeImmutable($row['expires_at']) <= new \DateTimeImmutable('now')) {
        $this->db->rollBack();
        return ['status'=>401,'body'=>['error'=>'activation_invalid_or_expired']];
      }
      $employee = $this->db->prepare('SELECT employee_id FROM onsite_employees WHERE employee_id = ? AND active = 1');
      $employee->execute([$row['employee_id']]);
      if ($employee->fetchColumn() === false) {
        $this->db->rollBack();
        return ['status'=>403,'body'=>['error'=>'employee_not_active']];
      }
      $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
      $this->devices->register($token, $row['employee_id']);
      $consume = $this->db->prepare('UPDATE onsite_activation_tokens SET consumed_at = ? WHERE token_hash = ? AND consumed_at IS NULL');
      $consume->execute([(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'), $hash]);
      if ($consume->rowCount() !== 1) throw new RuntimeException('Activation replay');
      $this->db->commit();
      return ['status'=>200,'body'=>['device_token'=>$token]];
    } catch (\Throwable) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      return ['status'=>503,'body'=>['error'=>'activation_unavailable']];
    }
  }
}
