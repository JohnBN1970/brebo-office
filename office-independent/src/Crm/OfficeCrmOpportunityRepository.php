<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use PDO;
use InvalidArgumentException;

final class OfficeCrmOpportunityRepository {
    public function __construct(private PDO $db) {}
    public function listForViews(): array {
        $stmt=$this->db->query("SELECT o.id,o.relation_id,o.title AS name,o.stage,o.next_action,DATE(o.next_action_at) AS due_date,r.display_name AS relation_name FROM office_crm_opportunities o JOIN office_crm_relations r ON r.id=o.relation_id WHERE (o.status='open' OR (o.status='closed' AND o.stage IN ('won','lost'))) ORDER BY o.next_action_at IS NULL,o.next_action_at,o.id LIMIT 500");
        $items=$stmt->fetchAll(PDO::FETCH_ASSOC);
        // A won opportunity must remain visible even if the calculation transfer
        // has not been installed. Never infer that a project exists from 'won'.
        $transferTable=false;
        try {
            if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
                $check=$this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='office_calc_crm_acceptance_transfers'");
            } else {
                $check=$this->db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_crm_acceptance_transfers'");
            }
            $transferTable=(bool)$check->fetchColumn();
        } catch (\\Throwable $e) {
            $transferTable=false;
        }
        if ($transferTable) {
            $lookup=$this->db->prepare('SELECT office_project_id FROM office_calc_crm_acceptance_transfers WHERE crm_opportunity_id=? LIMIT 1');
            foreach ($items as &$item) {
                if ($item['stage']!=='won') continue;
                $lookup->execute([(string)$item['id']]);
                $project=$lookup->fetchColumn();
                if ($project!==false && (int)$project>0) $item['project_id']=(int)$project;
            }
            unset($item);
        }
        return $items;
    }
    public function create(int $relationId,string $title,?int $contactId=null): int {
        $title=trim($title);
        if ($relationId<1 || $title==='' || mb_strlen($title)>255 || ($contactId!==null && $contactId<1)) throw new InvalidArgumentException('Ongeldige verkoopkans.');
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
}
