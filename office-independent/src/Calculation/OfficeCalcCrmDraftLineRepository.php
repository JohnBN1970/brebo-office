<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;
use PDO;
use Throwable;

/** Project-free CRM Calc line persistence. All writes require live CRM access. */
final class OfficeCalcCrmDraftLineRepository
{
    public function __construct(private readonly PDO $db) {}

    public function save(int $draftId,int $actorId,string $lineId,string $costType,
        string $description,int $quantity,int $unitCostCents,?string $activityRef,
        callable $canManageOpportunity): void
    {
        if ($draftId<1 || $actorId<1 || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$lineId)
            || !in_array($costType,['labor','material','equipment','subcontract','other'],true)
            || trim($description)==='' || mb_strlen($description)>500
            || $quantity<1 || $unitCostCents<0 || $unitCostCents>100000000000
            || ($activityRef!==null && mb_strlen($activityRef)>191)
            || $this->db->inTransaction()) throw new DomainException('Invalid CRM Calc line');
        $this->db->beginTransaction();
        try {
            require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';
            if (!(new OfficeCalcCrmDraftAccess($this->db))
                ->canManage($actorId,$draftId,$canManageOpportunity)
                || !$this->db->inTransaction()) throw new DomainException('CRM Calc access denied');
            $q=$this->db->prepare('SELECT 1 FROM office_calc_crm_draft_lines WHERE draft_id=? AND line_id=?');
            $q->execute([$draftId,$lineId]);
            if ($q->fetchColumn()!==false) {
                $q=$this->db->prepare('UPDATE office_calc_crm_draft_lines SET cost_type=?,description=?,quantity=?,unit_cost_cents=?,activity_ref=?,updated_by=? WHERE draft_id=? AND line_id=?');
                $q->execute([$costType,trim($description),$quantity,$unitCostCents,$activityRef,$actorId,$draftId,$lineId]);
            } else {
                $q=$this->db->prepare('INSERT INTO office_calc_crm_draft_lines(draft_id,line_id,cost_type,description,quantity,unit_cost_cents,activity_ref,updated_by) VALUES(?,?,?,?,?,?,?,?)');
                $q->execute([$draftId,$lineId,$costType,trim($description),$quantity,$unitCostCents,$activityRef,$actorId]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function list(int $draftId,int $actorId,callable $canManageOpportunity): array
    {
        require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';
        (new OfficeCalcCrmDraftAccess($this->db))->assertCanManage($actorId,$draftId,$canManageOpportunity);
        $q=$this->db->prepare('SELECT line_id,cost_type,description,quantity,unit_cost_cents,activity_ref FROM office_calc_crm_draft_lines WHERE draft_id=? ORDER BY line_id');
        $q->execute([$draftId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
