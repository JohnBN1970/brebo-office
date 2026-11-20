<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use PDO;
require_once __DIR__.'/OfficeCrmDirectProjectTransfer.php';
require_once __DIR__.'/OfficeCrmNativeProjectAdapter.php';

/**
 * Explicit application service. The HTTP controller must validate the current
 * Office session, request CSRF and the user-intended accepted opportunity.
 * This does not start work or create a calculation / locked budget.
 */
final class OfficeCrmDirectProjectAcceptance
{
    public function __construct(private readonly PDO $db) {}

    public function transferForOperator(int $operatorId,int $opportunityId): int
    {
        // Check on every invocation, including idempotent repeat requests.
        // The adapter rechecks inside the creation transaction.
        if ($operatorId < 1 || $opportunityId < 1) {
            throw new \DomainException('Invalid transfer identity');
        }
        $actor=$this->db->prepare(
            "SELECT 1 FROM office_operator_accounts a
             JOIN office_module_permissions g ON g.operator_id=a.id
             WHERE a.id=? AND a.active=1 AND g.module='projects'
               AND g.permission='create' LIMIT 1"
        );
        $actor->execute([$operatorId]);
        if ($actor->fetchColumn()===false) {
            throw new \DomainException('Native project creation permission required');
        }
        $projectId = (new OfficeCrmDirectProjectTransfer($this->db))->transfer(
            $opportunityId,
            new OfficeCrmNativeProjectAdapter($operatorId)
        );
        // A second creator may be authorized to create projects but not to
        // view an existing project created by another operator. Do not leak
        // its ID through the idempotent direct-transfer response.
        $membership=$this->db->prepare(
            "SELECT 1 FROM office_project_memberships m
             JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
             WHERE m.project_id=? AND m.operator_id=?
               AND m.permission IN ('read','manage') LIMIT 1"
        );
        $membership->execute([$projectId,$operatorId]);
        if ($membership->fetchColumn()===false) {
            throw new \DomainException('Project membership required to open transferred project');
        }
        return $projectId;
    }
}
