<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use PDO;

/** Read-only Deal quantities for an authorized CRM calculation draft. */
final class OfficeCalcDealHousingReader
{
    public function __construct(private readonly PDO $db) {}

    public function forDraft(int $draftId): array
    {
        if ($draftId < 1) return [];
        $query = $this->db->prepare(
            'SELECT d.opportunity_id, h.building_id, b.building_name, h.housing_type_id, ' .
            't.code AS housing_type_code, t.name AS housing_type_name, h.dwelling_count, h.revision ' .
            'FROM office_calc_crm_drafts d ' .
            'INNER JOIN office_crm_opportunities o ON o.id=d.opportunity_id ' .
            'INNER JOIN office_crm_deal_housing_types h ON h.opportunity_id=d.opportunity_id ' .
            'INNER JOIN office_housing_types t ON t.id=h.housing_type_id AND t.building_id=h.building_id ' .
            'INNER JOIN office_buildings b ON b.id=h.building_id ' .
            "WHERE d.id=? AND d.project_id IS NULL AND o.status='open' " .
            'ORDER BY b.building_name,t.code'
        );
        $query->execute([$draftId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}
