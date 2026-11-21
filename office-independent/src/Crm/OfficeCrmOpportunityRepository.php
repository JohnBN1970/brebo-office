<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use PDO;
use InvalidArgumentException;
require_once __DIR__.'/OfficeCrmProjectHandoff.php';

final class OfficeCrmOpportunityRepository {
    public function __construct(private PDO $db) {}
    public function listForViews(): array {
        $stmt=$this->db->query("SELECT o.id,o.relation_id,o.title AS name,o.stage,o.next_action,DATE(o.next_action_at) AS due_date,r.display_name AS relation_name FROM office_crm_opportunities o JOIN office_crm_relations r ON r.id=o.relation_id WHERE (o.status='open' OR (o.status='closed' AND o.stage IN ('won','lost'))) ORDER BY o.next_action_at IS NULL,o.next_action_at,o.id LIMIT 500");
        $items=$stmt->fetchAll(PDO::FETCH_ASSOC);
        // Resolve only persisted project links, from either accepted transfer path.
        $handoff = new OfficeCrmProjectHandoff($this->db);
        foreach ($items as &$item) {
            if ($item['stage'] !== 'won') continue;
            try {
                $projectId = $handoff->projectId((int)$item['id']);
                if ($projectId !== null) $item['project_id'] = $projectId;
            } catch (\PDOException $e) {
                // Fail closed in the display: do not claim a project exists.
            }
        }
        unset($item);
        return $items;
    }
    public function create(int $relationId,string $title,?int $contactId=null): int {
        $title=trim($title);
        if ($relationId<1 || $title==='' || mb_strlen($title)>255 || ($contactId!==null && $contactId<1)) throw new InvalidArgumentException('Ongeldige deal.');
        $stmt=$this->db->prepare("INSERT INTO office_crm_opportunities (relation_id,contact_id,title,stage,currency) VALUES (?,?,?,'prospect','EUR')");
        $stmt->execute([$relationId,$contactId,$title]);
        return (int)$this->db->lastInsertId();
    }
    public function transition(int $id,string $from,string $to): bool {
        if ($id<1 || !OfficeCrmFunnel::canTransition($from,$to)) return false;
        $stmt=$this->db->prepare("UPDATE office_crm_opportunities SET stage=?,status=? WHERE id=? AND stage=? AND status='open'");
        $stmt->execute([$to,in_array($to,['won','lost'],true)?'closed':'open',$id,$from]);
        return $stmt->rowCount()===1;
    }
    /**
     * Correct a mistaken acceptance only before either project-transfer path is used.
     * Audit and status correction are atomic. Missing mapping schema fails closed.
     */
    public function correctUntransferredWon(int $id, string $to, int $operatorId, string $reason): bool {
        $reason=trim($reason);
        if ($id<1 || $operatorId<1 || !in_array($to,['opportunity','proposal','negotiation'],true)
            || mb_strlen($reason)<10 || mb_strlen($reason)>160) return false;
        $this->db->beginTransaction();
        try {
            $lock=$this->db->prepare("SELECT stage,status FROM office_crm_opportunities WHERE id=? FOR UPDATE");
            $lock->execute([$id]);
            $row=$lock->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['stage']!=='won' || $row['status']!=='closed') {
                $this->db->rollBack(); return false;
            }
            foreach ([['office_calc_crm_acceptance_transfers','crm_opportunity_id'],
                      ['office_crm_direct_project_transfers','opportunity_id']] as [$table,$key]) {
                $check=$this->db->prepare("SELECT 1 FROM {$table} WHERE {$key}=? LIMIT 1");
                $check->execute([$id]);
                if ($check->fetchColumn()!==false) { $this->db->rollBack(); return false; }
            }
            $audit=$this->db->prepare("INSERT INTO office_crm_opportunity_activities (opportunity_id,activity_type,title) VALUES (?,'correction',?)");
            $audit->execute([$id,'Correctie akkoord naar '.$to.' (#'.$operatorId.'): '.$reason]);
            $update=$this->db->prepare("UPDATE office_crm_opportunities SET stage=?,status='open' WHERE id=? AND stage='won' AND status='closed'");
            $update->execute([$to,$id]);
            if ($update->rowCount()!==1) { $this->db->rollBack(); return false; }
            $this->db->commit(); return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

}
