<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;
use PDO;

/**
 * CRM-first Calc identity; does not issue project Calc grants or launch tokens.
 * Authorization must be supplied by an authenticated CRM opportunity policy.
 */
final class OfficeCalcCrmDraftIdentity
{
    public function __construct(private readonly PDO $db) {}

    public function findOrCreate(int $opportunityId, int $crmRevision, int $actorId,
        callable $canManageOpportunity): int
    {
        if ($opportunityId < 1 || $crmRevision < 1 || $actorId < 1
            || $this->db->inTransaction()
            || $this->db->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new DomainException('Invalid CRM Calc draft request');
        }
        $this->db->beginTransaction();
        try {
            $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $q = $this->db->prepare('SELECT revision,stage,status FROM office_crm_opportunities WHERE id=?'.$lock);
            $q->execute([$opportunityId]);
            $opportunity = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($opportunity) || (int)$opportunity['revision'] !== $crmRevision
                || !in_array($opportunity['stage'],['prospect','opportunity','proposal','negotiation'],true)
                || $opportunity['status'] !== 'open'
                || $canManageOpportunity($this->db,$actorId,$opportunityId) !== true
                || !$this->db->inTransaction()) {
                throw new DomainException('CRM Calc draft access denied or stale opportunity');
            }
            $q = $this->db->prepare('SELECT id,crm_revision,project_id FROM office_calc_crm_drafts WHERE opportunity_id=?'.$lock);
            $q->execute([$opportunityId]);
            $existing = $q->fetch(PDO::FETCH_ASSOC);
            if (is_array($existing)) {
                if ((int)$existing['crm_revision'] !== $crmRevision || $existing['project_id'] !== null) {
                    throw new DomainException('CRM Calc draft revision changed or already transferred');
                }
                $id = (int)$existing['id'];
            } else {
                $q = $this->db->prepare('INSERT INTO office_calc_crm_drafts(opportunity_id,crm_revision,created_by) VALUES (?,?,?)');
                $q->execute([$opportunityId,$crmRevision,$actorId]);
                $id = (int)$this->db->lastInsertId();
            }
            if ($id < 1 || !$this->db->inTransaction()) throw new DomainException('CRM Calc draft not persisted');
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
