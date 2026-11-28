<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use PDO;

/** Called only inside the authorized definitive award transaction.
 * Does not infer dates or authorize a commercial decision.
 */
final class OfficeCrmAwardRecorder {
    public function __construct(private readonly PDO $db){}
    public function record(int $dealId,int $actorId):void {
        if(!$this->db->inTransaction()||$dealId<1||$actorId<1)throw new \DomainException('Authorized award transaction required');
        $driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if(!in_array($driver,['mysql','sqlite'],true))throw new \DomainException('Unsupported database');
        $lock=$driver==='mysql'?' FOR UPDATE':'';
        $q=$this->db->prepare("SELECT o.relation_id,r.relation_kind FROM office_crm_opportunities o JOIN office_crm_relations r ON r.id=o.relation_id WHERE o.id=? AND o.stage='won' AND o.status IN ('closed','won') AND r.status='active'".$lock);
        $q->execute([$dealId]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new \DomainException('Active relation and definitive award required');
        $q=$this->db->prepare('SELECT relation_id FROM office_crm_award_events WHERE opportunity_id=?'.$lock);
        $q->execute([$dealId]);$existing=$q->fetchColumn();
        if($existing===false){
            $q=$this->db->prepare('INSERT INTO office_crm_award_events(opportunity_id,relation_id,relation_kind,awarded_at,actor_id,source) VALUES(?,?,?,?,?,?)');
            $q->execute([$dealId,$row['relation_id'],$row['relation_kind'],gmdate('Y-m-d H:i:s'),$actorId,'definitive_award']);
        }elseif((int)$existing!==(int)$row['relation_id'])throw new \DomainException('Award relation mismatch');
        $q=$this->db->prepare("SELECT 1 FROM office_crm_relation_roles WHERE relation_id=? AND role_code='customer'");
        $q->execute([$row['relation_id']]);
        if($q->fetchColumn()===false){
            $q=$this->db->prepare("INSERT INTO office_crm_relation_roles(relation_id,role_code) VALUES(?,'customer')");
            $q->execute([$row['relation_id']]);
        }
    }
}
