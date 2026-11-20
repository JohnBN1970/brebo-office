<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use PDO;
use RuntimeException;

/**
 * Converts an existing lead into a CRM opportunity, atomically.
 * Source/campaign remain on the archived lead. No project is created here.
 * Caller must authenticate, authorize and verify the target customer relation.
 */
final class OfficeCrmLeadConversion
{
    public function __construct(private PDO $db) {}

    public function convert(int $leadId, int $customerRelationId, string $actorId): int
    {
        if ($leadId < 1 || $customerRelationId < 1 || trim($actorId) === '') {
            throw new \InvalidArgumentException('Invalid lead conversion.');
        }
        $this->db->beginTransaction();
        try {
            $select = $this->db->prepare("SELECT id,title,stage,status,converted_opportunity_id
                FROM office_crm_leads WHERE id=? FOR UPDATE");
            $select->execute([$leadId]);
            $lead = $select->fetch(PDO::FETCH_ASSOC);
            if (!$lead) throw new RuntimeException('Lead not found.');
            if ($lead['converted_opportunity_id'] !== null) {
                $id = (int)$lead['converted_opportunity_id'];
                $this->db->commit();
                return $id; // Idempotent retry, no duplicate opportunity.
            }
            if ($lead['status'] !== 'active') throw new RuntimeException('Lead is not active.');
            $relation = $this->db->prepare("SELECT id FROM office_crm_relations WHERE id=? AND status='active' LIMIT 1");
            $relation->execute([$customerRelationId]);
            if (!$relation->fetchColumn()) throw new RuntimeException('Relation not active.');
            $insert = $this->db->prepare("INSERT INTO office_crm_opportunities
                (relation_id,title,stage,status) VALUES (?,?,'prospect','open')");
            $insert->execute([$customerRelationId,$lead['title']]);
            $opportunityId = (int)$this->db->lastInsertId();
            $update = $this->db->prepare("UPDATE office_crm_leads
                SET relation_id=?,converted_opportunity_id=?,converted_at=NOW(),status='converted',stage='converted'
                WHERE id=? AND status='active' AND converted_opportunity_id IS NULL");
            $update->execute([$customerRelationId,$opportunityId,$leadId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Lead conversion conflict.');
            $event = $this->db->prepare("INSERT INTO office_crm_lead_events
                (lead_id,actor_operator_id,event_type,old_stage,new_stage,note)
                VALUES (?,?,'converted',?,'converted',?)");
            $event->execute([$leadId,$actorId,$lead['stage'],'Opportunity #'.$opportunityId]);
            $this->db->commit();
            return $opportunityId;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
