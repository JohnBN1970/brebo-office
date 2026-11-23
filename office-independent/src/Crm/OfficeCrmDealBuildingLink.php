<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;
use InvalidArgumentException;
use RuntimeException;

/**
 * Canonical, explicit deal-to-building link. Caller must authorize CRM writes
 * and verify CSRF; this service enforces an active deal and optimistic revision.
 * Requires reviewed installation of office_crm_deal_buildings.
 */
final class OfficeCrmDealBuildingLink
{
    public function __construct(private PDO $db) {}

    public function buildingId(int $dealId): ?int
    {
        if ($dealId < 1) throw new InvalidArgumentException('Invalid deal');
        $stmt = $this->db->prepare('SELECT building_id FROM office_crm_deal_buildings WHERE opportunity_id=?');
        $stmt->execute([$dealId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    }

    public function assign(int $dealId, int $buildingId, int $expectedRevision): void
    {
        if ($dealId < 1 || $buildingId < 1 || $expectedRevision < 1) {
            throw new InvalidArgumentException('Invalid deal building assignment');
        }
        $this->db->beginTransaction();
        try {
            $deal = $this->db->prepare('SELECT revision,stage,status FROM office_crm_opportunities WHERE id=? FOR UPDATE');
            $deal->execute([$dealId]);
            $row = $deal->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['revision'] !== $expectedRevision ||
                $row['status'] !== 'open' || in_array($row['stage'], ['won','lost'], true)) {
                throw new RuntimeException('Deal unavailable or changed');
            }
            $building = $this->db->prepare('SELECT id FROM office_buildings WHERE id=?');
            $building->execute([$buildingId]);
            if (!$building->fetchColumn()) throw new RuntimeException('Building not found');
            $existing = $this->buildingId($dealId);
            if ($existing !== $buildingId) {
                $write = $this->db->prepare('INSERT INTO office_crm_deal_buildings (opportunity_id,building_id)
                    VALUES (?,?) ON DUPLICATE KEY UPDATE building_id=VALUES(building_id)');
                $write->execute([$dealId,$buildingId]);
                $update = $this->db->prepare('UPDATE office_crm_opportunities SET revision=revision+1 WHERE id=? AND revision=?');
                $update->execute([$dealId,$expectedRevision]);
                if ($update->rowCount() !== 1) throw new RuntimeException('Deal revision conflict');
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
