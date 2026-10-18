<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use PDO;
use RuntimeException;

/**
 * Privileged Office enrollment orchestration.
 * CRM edit rights alone do not grant device issuance.
 * Requires an explicit, active Office role grant and employee enrollment scope.
 */
final class OnSiteOperatorActivationService {
  public function __construct(private PDO $db, private OnSiteActivationIssuer $issuer) {}

  /** @return array{activation_token:string,expires_at:string} */
  public function issue(WebsiteCrmOperatorContext $operator, string $employeeId): array {
    if ($employeeId === '' || strlen($employeeId) > 191) throw new RuntimeException('Invalid employee');
    if ($operator->sessionId === '' || strlen($operator->sessionId) !== 64 || !ctype_xdigit($operator->sessionId)) {
      throw new RuntimeException('Verified Office session required');
    }
    $check = $this->db->prepare('SELECT 1 FROM onsite_enrollment_operator_grants g
      INNER JOIN office_operator_accounts o ON o.id = g.operator_id AND o.active = 1
      INNER JOIN office_operator_sessions s ON s.operator_id = o.id
        AND s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > UTC_TIMESTAMP()
      WHERE g.operator_id = ? AND g.active = 1 LIMIT 1');
    $check->execute([$operator->sessionId, $operator->operatorId]);
    if ($check->fetchColumn() === false) throw new RuntimeException('Device enrollment permission required');
    // Persist the audit and token in one transaction: never issue an
    // untraceable activation secret if the audit insert fails.
    $this->db->beginTransaction();
    try {
      $issued = $this->issuer->issue($employeeId, true);
      $audit = $this->db->prepare('INSERT INTO onsite_activation_issue_audit
        (operator_id,employee_id,token_hash,issued_at,expires_at) VALUES (?,?,?,?,?)');
      $audit->execute([
        $operator->operatorId,
        $employeeId,
        hash('sha256', $issued['activation_token']),
        (new \\DateTimeImmutable('now', new \\DateTimeZone('UTC')))->format('c'),
        $issued['expires_at'],
      ]);
      $this->db->commit();
      return $issued;
    } catch (\\Throwable $error) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $error;
    }
  }
}
