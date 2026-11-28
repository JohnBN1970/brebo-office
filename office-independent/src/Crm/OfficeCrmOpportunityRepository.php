<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use PDO;
use InvalidArgumentException;
require_once __DIR__.'/OfficeCrmProjectHandoff.php';

final class OfficeCrmOpportunityRepository {
    public function __construct(private PDO $db) {}
    public function listForViews(): array {
        $stmt=$this->db->query("SELECT o.id,o.relation_id,o.title AS name,o.stage,o.next_action,DATE(o.next_action_at) AS due_date,r.display_name AS relation_name FROM office_crm_opportunities o JOIN office_crm_relations r ON r.id=o.relation_id WHERE (o.status='open' OR (o.status IN ('closed','won') AND o.stage IN ('won','lost'))) ORDER BY o.next_action_at IS NULL,o.next_action_at,o.id LIMIT 500");
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
    public function transition(int $id,string $from,string $to,?int $actorId=null): bool {
        if ($id<1 || !OfficeCrmFunnel::canTransition($from,$to)) return false;
        $recordAward=$to==='won' && getenv('OFFICE_CRM_AWARD_HISTORY_SCHEMA_VERIFIED')==='enabled';
        if ($recordAward) {
            if (!$actorId || $this->db->inTransaction()) throw new \DomainException('Award transaction requires operator');
            $this->db->beginTransaction();
        }
        try {
            if ($recordAward) {
                $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                $actor=$this->db->prepare('SELECT id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1'.$lock);
                $actor->execute([$actorId]);
                if (!$actor->fetchColumn()) throw new \DomainException('CRM write permission required');
            }
            $stmt=$this->db->prepare("UPDATE office_crm_opportunities SET stage=?,status=?,revision=revision+1 WHERE id=? AND stage=? AND status='open'");
            $stmt->execute([$to,in_array($to,['won','lost'],true)?'closed':'open',$id,$from]);
            $ok=$stmt->rowCount()===1;
            if ($recordAward) {
                if ($ok) {
                    require_once __DIR__.'/OfficeCrmAwardRecorder.php';
                    (new OfficeCrmAwardRecorder($this->db))->record($id,$actorId);
                    $this->db->commit();
                } else $this->db->rollBack();
            }
            return $ok;
        } catch (\Throwable $e) {
            if ($recordAward && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
