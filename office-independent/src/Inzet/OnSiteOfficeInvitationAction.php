<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

use PDO;
use RuntimeException;

/**
 * Authorization boundary for Office-native OnSite invitations.
 * Controller must independently enforce a verified Office session, HTTPS,
 * same-origin POST and session-bound CSRF before calling this action.
 */
final class OnSiteOfficeInvitationAction {
  public function __construct(private PDO $db, private OnSiteActivationInvitationIssuer $issuer) {}

  /** @return array{activation_token:string,expires_at:string} */
  public function issue(string $operatorId, int $projectId, int $personId): array {
    if ($operatorId === '' || strlen($operatorId) > 128 || $projectId <= 0 || $personId <= 0) {
      throw new RuntimeException('Invalid Office invitation context');
    }
    $authorize = function () use ($operatorId, $projectId, $personId): void {
    $check = $this->db->prepare("SELECT 1 FROM office_inzet_project_team t
      INNER JOIN office_project_memberships m ON m.project_id = t.project_id
      INNER JOIN office_operator_accounts a ON a.id = m.operator_id
      INNER JOIN office_inzet_people p ON p.id = t.person_id
      WHERE t.project_id = ? AND t.person_id = ? AND p.active = 1
        AND m.operator_id = ? AND m.permission = 'manage' AND a.active = 1 LIMIT 1 FOR UPDATE");
    $check->execute([$projectId, $personId, $operatorId]);
    if ($check->fetchColumn() === false) throw new RuntimeException('Office operator not authorized for employee');
    };
    return $this->issuer->issue($personId, 15, $projectId, $authorize);
  }
}
