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
        $ids = $this->resolveAll($opportunityId);
        return count($ids) === 1 ? $ids[0] : null;
    }

    /** @return list<int> */
    public function resolveAll(int $opportunityId): array
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
        if (count($ids) > 1) return []; // Conflicting converted lead records.
        $leadBuilding = count($ids) === 1 ? $ids[0] : null;
        if (!$this->explicitLinkSchemaVerified) return $ids;

        $explicit = $this->db->prepare(
            'SELECT l.building_id FROM office_crm_deal_buildings l
             INNER JOIN office_buildings b ON b.id=l.building_id
             WHERE l.opportunity_id=:opportunity_id ORDER BY l.building_id'
        );
        $explicit->execute([':opportunity_id' => $opportunityId]);
        $explicitIds = array_map('intval', $explicit->fetchAll(PDO::FETCH_COLUMN));
        if ($explicitIds === []) return $ids;
        // Preserve legacy conflict protection; never select an arbitrary building.
        if ($leadBuilding !== null && !in_array($leadBuilding, $explicitIds, true)) return [];
        return $explicitIds;
    }
}
