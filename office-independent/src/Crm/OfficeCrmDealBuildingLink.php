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

    /** @return list<int> */
    public function buildingIds(int $dealId): array
    {
        if ($dealId < 1) throw new InvalidArgumentException('Invalid deal');
        $stmt = $this->db->prepare('SELECT building_id FROM office_crm_deal_buildings WHERE opportunity_id=? ORDER BY building_id');
        $stmt->execute([$dealId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function buildingId(int $dealId): ?int
    {
        $ids = $this->buildingIds($dealId);
        if (count($ids) > 1) throw new RuntimeException('Multiple buildings: use buildingIds');
        return $ids[0] ?? null;
    }

    /**
     * Replace the explicitly selected set atomically. Requires composite PK,
     * caller authorization, CSRF and audit integration before UI activation.
     * Empty sets are allowed for deals not yet scoped.
     * @param list<int> $buildingIds
     */
    public function replaceBuildings(int $dealId, array $buildingIds, int $expectedRevision, ?int $auditOperatorId=null): void
    {
        if ($dealId < 1 || $expectedRevision < 1) {
            throw new InvalidArgumentException('Invalid deal or revision');
        }
        foreach ($buildingIds as $id) {
            if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Invalid building');
        }
        $ids = array_values(array_unique($buildingIds));
        sort($ids, SORT_NUMERIC);
        $ownsTransaction=!$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $deal = $this->db->prepare(
                'SELECT revision,stage,status FROM office_crm_opportunities WHERE id=?'
                . ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' ? '' : ' FOR UPDATE')
            );
            $deal->execute([$dealId]);
            $row = $deal->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['revision'] !== $expectedRevision ||
                $row['status'] !== 'open' || in_array($row['stage'], ['won','lost'], true)) {
                throw new RuntimeException('Deal unavailable or changed');
            }
            $check = $this->db->prepare('SELECT id FROM office_buildings WHERE id=?');
            foreach ($ids as $id) {
                $check->execute([$id]);
                if (!$check->fetchColumn()) throw new RuntimeException('Building not found');
                $check->closeCursor();
            }
            $existing = $this->buildingIds($dealId);
            if ($existing !== $ids) {
                $remove = $this->db->prepare('DELETE FROM office_crm_deal_buildings WHERE opportunity_id=? AND building_id=?');
                foreach (array_diff($existing, $ids) as $id) $remove->execute([$dealId, $id]);
                $add = $this->db->prepare('INSERT INTO office_crm_deal_buildings (opportunity_id,building_id) VALUES (?,?)');
                foreach (array_diff($ids, $existing) as $id) $add->execute([$dealId, $id]);
                if ($auditOperatorId!==null) {
                    if (getenv('OFFICE_BUILDING_AUDIT_SCHEMA_VERIFIED')!=='enabled') throw new RuntimeException('building_audit_unavailable');
                    require_once dirname(__DIR__).'/Buildings/OfficeBuildingAudit.php';
                    $audit=new \Brebo\Office\Buildings\OfficeBuildingAudit($this->db);
                    foreach (array_diff($existing,$ids) as $id) $audit->record($id,$auditOperatorId,'unlinked','deal',$dealId,['linked'=>true],['linked'=>false]);
                    foreach (array_diff($ids,$existing) as $id) $audit->record($id,$auditOperatorId,'linked','deal',$dealId,['linked'=>false],['linked'=>true]);
                }
                if ($this->buildingIds($dealId) !== $ids) throw new RuntimeException('Building set mismatch');
                $update = $this->db->prepare('UPDATE office_crm_opportunities SET revision=revision+1 WHERE id=? AND revision=?');
                $update->execute([$dealId,$expectedRevision]);
                if ($update->rowCount() !== 1) throw new RuntimeException('Deal revision conflict');
            }
            if ($ownsTransaction) $this->db->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function assign(int $dealId, int $buildingId, int $expectedRevision): void
    {
        if ($dealId < 1 || $buildingId < 1 || $expectedRevision < 1) {
            throw new InvalidArgumentException('Invalid deal building assignment');
        }
        $this->db->beginTransaction();
        try {
            $deal = $this->db->prepare(
                'SELECT revision,stage,status FROM office_crm_opportunities WHERE id=?'
                . ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' ? '' : ' FOR UPDATE')
            );
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
                if ($existing !== null) {
                    $delete = $this->db->prepare('DELETE FROM office_crm_deal_buildings WHERE opportunity_id=? AND building_id=?');
                    $delete->execute([$dealId,$existing]);
                }
                $write = $this->db->prepare('INSERT INTO office_crm_deal_buildings (opportunity_id,building_id) VALUES (?,?)');
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
