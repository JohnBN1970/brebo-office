<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use DomainException;
use PDO;

/**
 * Atomic, idempotent direct-order transfer. No calculation or budget is fabricated.
 * Requires installed mapping schema and an authorized caller.
 */
final class OfficeCrmDirectProjectTransfer
{
    public function __construct(private readonly PDO $db) {}

    public function transfer(int $opportunityId, callable $createProject): int
    {
        if ($opportunityId < 1 || $this->db->inTransaction()) {
            throw new DomainException('Invalid direct transfer transaction');
        }
        $driver=(string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver,['mysql','sqlite'],true)) {
            throw new DomainException('Unsupported transfer database driver');
        }
        $lock=$driver==='mysql' ? ' FOR UPDATE' : '';
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id,relation_id,title,stage,status FROM office_crm_opportunities WHERE id=?'.$lock);
            $q->execute([$opportunityId]);
            $opportunity=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($opportunity) || $opportunity['stage']!=='won'
                || !in_array($opportunity['status'],['won','closed'],true)) {
                throw new DomainException('Opportunity is not accepted');
            }
            $existing=$this->db->prepare('SELECT project_id FROM office_crm_direct_project_transfers WHERE opportunity_id=?'.$lock);
            $existing->execute([$opportunityId]);
            $projectId=$existing->fetchColumn();
            $calc=$this->db->prepare('SELECT office_project_id FROM office_calc_crm_acceptance_transfers WHERE crm_opportunity_id=? LIMIT 1'.$lock);
            $calc->execute([(string)$opportunityId]);
            if ($calc->fetchColumn()!==false) {
                throw new DomainException('Calculation project transfer already exists');
            }
            if ($projectId!==false) {
                $this->db->commit();
                return (int)$projectId;
            }
            // Adapter must persist a native Office project using this same PDO transaction.
            $projectId=$createProject($this->db,$opportunity);
            if (!is_int($projectId) || $projectId<1 || !$this->db->inTransaction()) {
                throw new DomainException('Native project creation failed');
            }
            $verify=$this->db->prepare('SELECT id FROM office_projects WHERE id=?');
            $verify->execute([$projectId]);
            if ((int)$verify->fetchColumn()!==$projectId) {
                throw new DomainException('Native project was not persisted');
            }
            $save=$this->db->prepare('INSERT INTO office_crm_direct_project_transfers (opportunity_id,project_id,relation_id) VALUES (?,?,?)');
            $save->execute([$opportunityId,$projectId,(int)$opportunity['relation_id']]);
            $this->db->commit();
            return $projectId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
