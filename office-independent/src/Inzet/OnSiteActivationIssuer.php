<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Privileged application service. Never call from anonymous HTTP routes.
 * Caller MUST independently authenticate the Office operator and authorize
 * employee-device enrollment before invoking issue().
 */
final class OnSiteActivationIssuer {
  public function __construct(private PDO $db) {}

  /** @return array{activation_token:string,expires_at:string} */
  public function issue(string $employeeId, bool $operatorAuthorized, int $ttlMinutes = 10): array {
    if (!$operatorAuthorized) throw new RuntimeException('Operator authorization required');
    if ($employeeId === '' || $ttlMinutes < 1 || $ttlMinutes > 30) {
      throw new InvalidArgumentException('Invalid activation request');
    }
    $q = $this->db->prepare('SELECT employee_id FROM onsite_employees WHERE employee_id = ? AND active = 1');
    $q->execute([$employeeId]);
    if ($q->fetchColumn() === false) throw new RuntimeException('Employee not active');
    $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
      ->modify('+' . $ttlMinutes . ' minutes')->format('c');
    $insert = $this->db->prepare('INSERT INTO onsite_activation_tokens (token_hash,employee_id,expires_at,consumed_at) VALUES (?,?,?,NULL)');
    $insert->execute([hash('sha256',$secret),$employeeId,$expires]);
    return ['activation_token'=>$secret,'expires_at'=>$expires];
  }
}
