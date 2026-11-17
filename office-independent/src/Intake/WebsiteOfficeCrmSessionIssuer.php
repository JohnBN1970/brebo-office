<?php
declare(strict_types=1);
namespace Brebo\Office\Intake;
use PDO;
final class WebsiteOfficeCrmSessionIssuer {
  public function __construct(private readonly PDO $db) {}
  public function issue(string $operatorId,int $now): string {
    $token=bin2hex(random_bytes(32));
    $q=$this->db->prepare('INSERT INTO office_operator_sessions (token_hash,operator_id,expires_at,revoked_at) VALUES (?,?,?,NULL)');
    $q->execute([hash('sha256',$token),$operatorId,gmdate('Y-m-d H:i:s',$now+28800)]);
    return $token;
  }
  public function revoke(string $token): void {
    $q=$this->db->prepare('UPDATE office_operator_sessions SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=? AND revoked_at IS NULL');
    $q->execute([hash('sha256',$token)]);
  }
}
