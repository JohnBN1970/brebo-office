<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;
use PDO;
use DomainException;

final class OfficeDealProjectBuildingTransfer {
    public function __construct(private PDO $db) {}
    /** Caller must hold the accepted Deal lock and own the transaction. */
    public function transfer(int $dealId,int $projectId): void {
        if (!$this->db->inTransaction() || $dealId<1 || $projectId<1) throw new DomainException('Invalid building transfer');
        $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $q=$this->db->prepare('SELECT id FROM office_projects WHERE id=?'.$lock);
        $q->execute([$projectId]);
        if (!$q->fetchColumn()) throw new DomainException('Project missing');
        $q=$this->db->prepare('SELECT building_id FROM office_crm_deal_buildings WHERE opportunity_id=? ORDER BY building_id'.$lock);
        $q->execute([$dealId]);
        $ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $q=$this->db->prepare('SELECT building_id FROM office_project_buildings WHERE project_id=? ORDER BY building_id'.$lock);
        $q->execute([$projectId]);
        $existing=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if (array_diff($existing,$ids)) throw new DomainException('Project contains buildings outside accepted Deal');
        $active=$this->db->prepare('SELECT id FROM office_buildings WHERE id=? AND active=1'.$lock);
        $insert=$this->db->prepare('INSERT INTO office_project_buildings (project_id,building_id) VALUES (?,?)');
        foreach ($ids as $id) {
            $active->execute([$id]);
            if (!$active->fetchColumn()) throw new DomainException('Accepted building unavailable');
            if (!in_array($id,$existing,true)) $insert->execute([$projectId,$id]);
        }
        $q->execute([$projectId]);
        if (array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN))!==$ids) throw new DomainException('Building transfer verification failed');
    }
}
