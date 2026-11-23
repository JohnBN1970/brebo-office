<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use DomainException;
use PDO;

/** Copies accepted Deal housing quantities in the existing project-creation transaction. */
final class OfficeCrmDealHousingProjectTransfer
{
    public function __construct(private readonly PDO $db) {}

    public function transfer(int $dealId, int $projectId): int
    {
        if ($dealId < 1 || $projectId < 1 || !$this->db->inTransaction()) {
            throw new DomainException('Housing transfer requires an active acceptance transaction');
        }
        $read=$this->db->prepare(
            'SELECT h.building_id,h.housing_type_id,h.dwelling_count FROM office_crm_deal_housing_types h ' .
            'INNER JOIN office_housing_types t ON t.id=h.housing_type_id AND t.building_id=h.building_id ' .
            'WHERE h.opportunity_id=? ORDER BY h.building_id,h.housing_type_id'
        );
        $read->execute([$dealId]);
        $rows=$read->fetchAll(PDO::FETCH_ASSOC);
        if ($rows===[]) return 0;
        $check=$this->db->prepare('SELECT 1 FROM office_project_housing_types WHERE project_id=? LIMIT 1');
        $check->execute([$projectId]);
        if ($check->fetchColumn()!==false) throw new DomainException('Project housing distribution already exists');
        $link=$this->db->prepare('INSERT INTO office_project_buildings (project_id,building_id) VALUES (?,?)');
        $existing=$this->db->prepare('SELECT 1 FROM office_project_buildings WHERE project_id=? AND building_id=? LIMIT 1');
        $insert=$this->db->prepare(
            'INSERT INTO office_project_housing_types (project_id,building_id,housing_type_id,dwelling_count) VALUES (?,?,?,?)'
        );
        $linked=[];
        foreach ($rows as $row) {
            $building=(int)$row['building_id'];
            $type=(int)$row['housing_type_id'];
            $count=(int)$row['dwelling_count'];
            if ($building<1 || $type<1 || $count<1) throw new DomainException('Invalid accepted Deal housing scope');
            if (!isset($linked[$building])) {
                $existing->execute([$projectId,$building]);
                if ($existing->fetchColumn()===false) $link->execute([$projectId,$building]);
                $linked[$building]=true;
            }
            $insert->execute([$projectId,$building,$type,$count]);
        }
        return count($rows);
    }
}
