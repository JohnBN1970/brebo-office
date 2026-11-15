<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;
use PDO;

/**
 * Explicit CRM draft access, independent of project membership.
 * Must be called only after authenticating the Office operator.
 * No session token or launch URL is issued by this policy.
 */
final class OfficeCalcCrmDraftAccess
{
    public function __construct(private readonly PDO $db) {}

    public function canManage(int $actorId, int $draftId, callable $canManageOpportunity): bool
    {
        if ($actorId < 1 || $draftId < 1) return false;
        $q = $this->db->prepare(
            'SELECT d.opportunity_id,d.crm_revision,d.project_id,o.revision,o.stage,o.status,a.active '.
            'FROM office_calc_crm_drafts d '.
            'INNER JOIN office_crm_opportunities o ON o.id=d.opportunity_id '.
            'INNER JOIN office_operator_accounts a ON a.id=? '.
            'WHERE d.id=?'
        );
        $q->execute([$actorId,$draftId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || (int)$row['active'] !== 1
            || $row['project_id'] !== null
            || (int)$row['crm_revision'] !== (int)$row['revision']
            || !in_array($row['stage'],['prospect','opportunity','proposal','negotiation'],true)
            || $row['status'] !== 'open') return false;
        return $canManageOpportunity($this->db,$actorId,(int)$row['opportunity_id']) === true;
    }

    public function assertCanManage(int $actorId, int $draftId, callable $canManageOpportunity): void
    {
        if (!$this->canManage($actorId,$draftId,$canManageOpportunity)) {
            throw new DomainException('CRM Calc draft access denied');
        }
    }
}
