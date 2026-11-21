<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * Persists supplementary FR-201 facts without duplicating lead master data.
 * Caller MUST authenticate operator, authorize lead access and validate CSRF.
 */
final class OfficeFr201Repository
{
    public function __construct(private readonly PDO $db) {}

    /** @return array{revision:int,data:array<string,string>} */
    public function load(int $leadId): array
    {
        if ($leadId < 1) throw new InvalidArgumentException('Invalid lead');
        $query = $this->db->prepare('SELECT revision,data_json FROM office_crm_lead_fr201 WHERE lead_id=?');
        $query->execute([$leadId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['revision'=>0,'data'=>[]];
        $data = json_decode((string)$row['data_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Invalid FR-201 data');
        return ['revision'=>(int)$row['revision'],'data'=>$data];
    }

    /**
     * Expected revision 0 creates the supplement; later writes require exact
     * revision match. Every accepted change appends an immutable audit snapshot.
     */
    public function save(int $leadId, string $actor, int $expectedRevision, array $values, string $reason): int
    {
        if ($leadId < 1 || trim($actor) === '' || $expectedRevision < 0 || trim($reason) === ''
            || mb_strlen($reason, 'UTF-8') > 500) {
            throw new InvalidArgumentException('Missing identity, revision or change reason');
        }
        require_once __DIR__.'/OfficeFr201Intake.php';
        $allowed = OfficeFr201Intake::fields();
        // CRM master facts must be edited in CRM, not silently overridden here.
        $masterFields = ['channel','source_reference','contact_name','contact_email','customer_question','relation_id','owner_user_id','received_at'];
        $data = [];
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $allowed) || in_array($key, $masterFields, true)
                || !is_string($value) || mb_strlen($value, 'UTF-8') > 10000) {
                throw new InvalidArgumentException('Invalid FR-201 field');
            }
            $data[$key] = trim($value);
        }
        // Persist only supplement fields; a missing submitted field must not erase
        // previously saved facts when the form is extended in later versions.
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->db->beginTransaction();
        try {
            if ($expectedRevision === 0) {
                $stmt = $this->db->prepare('INSERT INTO office_crm_lead_fr201 (lead_id,revision,data_json,updated_by) VALUES (?,1,?,?)');
                $stmt->execute([$leadId,$json,$actor]);
                $revision = 1;
            } else {
                $stmt = $this->db->prepare('UPDATE office_crm_lead_fr201 SET revision=revision+1,data_json=?,updated_by=? WHERE lead_id=? AND revision=?');
                $stmt->execute([$json,$actor,$leadId,$expectedRevision]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('FR-201 is gewijzigd; ververs het formulier');
                $revision = $expectedRevision + 1;
            }
            $event = $this->db->prepare('INSERT INTO office_crm_lead_fr201_events (lead_id,revision,actor_operator_id,reason,data_json) VALUES (?,?,?,?,?)');
            $event->execute([$leadId,$revision,$actor,trim($reason),$json]);
            $this->db->commit();
            return $revision;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
