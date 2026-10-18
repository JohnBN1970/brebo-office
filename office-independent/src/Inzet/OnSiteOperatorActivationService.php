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
    $check = $this->db->prepare('SELECT 1 FROM onsite_enrollment_operator_grants g
      INNER JOIN office_operator_accounts o ON o.id = g.operator_id AND o.active = 1
      WHERE g.operator_id = ? AND g.active = 1 LIMIT 1');
    $check->execute([$operator->operatorId]);
    if ($check->fetchColumn() === false) throw new RuntimeException('Device enrollment permission required');
    return $this->issuer->issue($employeeId, true);
  }
}
