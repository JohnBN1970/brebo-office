<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use DomainException;
use InvalidArgumentException;
use PDO;

/** Deal-scoped housing quantities; no project writes before acceptance. */
final class OfficeCrmDealHousingWriter
{
    public function __construct(private readonly PDO $db) {}

    public function save(int $dealId, int $buildingId, int $housingTypeId, int $count, int $actorId, ?int $expectedRevision = null): int
    {
        if (min($dealId, $buildingId, $housingTypeId, $count, $actorId) < 1 || $count > 100000
            || ($expectedRevision !== null && $expectedRevision < 0)) {
            throw new InvalidArgumentException('Ongeldige woningtypeverdeling.');
        }
        if ($this->db->inTransaction()) throw new DomainException('Transactie al actief.');
        $this->db->beginTransaction();
        try {
            $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
            $deal = $this->db->prepare('SELECT stage,status FROM office_crm_opportunities WHERE id=?'.$lock);
            $deal->execute([$dealId]);
            $state = $deal->fetch(PDO::FETCH_ASSOC);
            if (!is_array($state) || (string)$state['status'] !== 'open'
                || in_array($state['stage'], ['won','lost'], true)) {
                throw new DomainException('Deal is niet wijzigbaar.');
            }
            $type = $this->db->prepare('SELECT id FROM office_housing_types WHERE id=? AND building_id=?');
            $type->execute([$housingTypeId,$buildingId]);
            if ($type->fetchColumn() === false) throw new DomainException('Woningtype hoort niet bij dit gebouw.');
            $current = $this->db->prepare('SELECT dwelling_count,revision FROM office_crm_deal_housing_types WHERE opportunity_id=? AND building_id=? AND housing_type_id=?'.$lock);
            $current->execute([$dealId,$buildingId,$housingTypeId]);
            $row = $current->fetch(PDO::FETCH_ASSOC);
            $revision = $row === false ? 0 : (int)$row['revision'];
            if ($expectedRevision !== null && $expectedRevision !== $revision) {
                throw new DomainException('De woningtypeverdeling is intussen gewijzigd.');
            }
            if ($row !== false && (int)$row['dwelling_count'] === $count) {
                $this->db->commit();
                return $revision;
            }
            $next = $revision + 1;
            if ($row === false) {
                $write = $this->db->prepare('INSERT INTO office_crm_deal_housing_types (opportunity_id,building_id,housing_type_id,dwelling_count,revision,updated_by) VALUES (?,?,?,?,?,?)');
                $write->execute([$dealId,$buildingId,$housingTypeId,$count,$next,$actorId]);
            } else {
                $write = $this->db->prepare('UPDATE office_crm_deal_housing_types SET dwelling_count=?,revision=?,updated_by=? WHERE opportunity_id=? AND building_id=? AND housing_type_id=?');
                $write->execute([$count,$next,$actorId,$dealId,$buildingId,$housingTypeId]);
            }
            $event = $this->db->prepare('INSERT INTO office_crm_deal_housing_events (opportunity_id,building_id,housing_type_id,old_count,new_count,revision,changed_by) VALUES (?,?,?,?,?,?,?)');
            $event->execute([$dealId,$buildingId,$housingTypeId,$row === false ? null : (int)$row['dwelling_count'],$count,$next,$actorId]);
            $this->db->commit();
            return $next;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
