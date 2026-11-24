<?php
declare(strict_types=1);
namespace Brebo\Office\Project;

use PDO;

final class OfficeBuildingElementAccess
{
    public function __construct(private readonly PDO $db) {}

    public function canManageBeforeProject(int $actor,int $building,int $deal):bool
    {
        if($actor<1||$building<1||$deal<1)return false;
        if(getenv('OFFICE_CRM_DEAL_BUILDINGS_SCHEMA_VERIFIED')!=='enabled'
            ||getenv('OFFICE_BUILDING_ELEMENT_GRANTS_SCHEMA_VERIFIED')!=='enabled')return false;
        $q=$this->db->prepare("SELECT 1 FROM office_building_element_grants g
            JOIN office_operator_accounts a ON a.id=g.operator_id AND a.active=1 AND a.can_edit_crm=1
            JOIN office_crm_deal_buildings b ON b.opportunity_id=g.opportunity_id AND b.building_id=g.building_id
            JOIN office_crm_opportunities o ON o.id=b.opportunity_id
            WHERE g.operator_id=? AND g.building_id=? AND g.opportunity_id=?
            AND o.status='open' AND o.stage IN ('prospect','opportunity','proposal','negotiation')");
        $q->execute([$actor,$building,$deal]);
        return $q->fetchColumn()!==false;
    }
}
