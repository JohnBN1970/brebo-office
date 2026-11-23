<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Read-only CRM Deal -> canonical building identity.
 * Fail closed if multiple converted leads disagree; never guess by address.
 * This does not create a project or duplicate building records.
 */
final class OfficeCalcDealBuildingResolver
{
    public function __construct(private readonly PDO $db, private readonly bool $explicitLinkSchemaVerified = false) {}

    public function resolve(int $opportunityId): ?int
    {
        if ($opportunityId < 1) {
            throw new \InvalidArgumentException('Invalid deal ID');
        }
        $query = $this->db->prepare(
            'SELECT DISTINCT l.building_id
             FROM office_crm_leads l
             INNER JOIN office_crm_opportunities o
               ON o.id = l.converted_opportunity_id
             INNER JOIN office_buildings b ON b.id = l.building_id
             WHERE o.id = :opportunity_id AND l.building_id IS NOT NULL'
        );
        $query->execute([':opportunity_id' => $opportunityId]);
        $ids = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
        if (count($ids) > 1) return null; // Conflicting converted lead records.
        $leadBuilding = count($ids) === 1 ? $ids[0] : null;
        if (!$this->explicitLinkSchemaVerified) return $leadBuilding;

        $explicit = $this->db->prepare(
            'SELECT l.building_id FROM office_crm_deal_buildings l
             INNER JOIN office_buildings b ON b.id=l.building_id
             WHERE l.opportunity_id=:opportunity_id'
        );
        $explicit->execute([':opportunity_id' => $opportunityId]);
        $value = $explicit->fetchColumn();
        if ($value === false) return $leadBuilding;
        $explicitBuilding = (int)$value;
        // Never silently choose between disagreeing canonical and lead buildings.
        return $leadBuilding !== null && $leadBuilding !== $explicitBuilding
            ? null : $explicitBuilding;
    }
}
