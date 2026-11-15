<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;
require_once __DIR__.'/OfficeApprovedLabourCostPreview.php';

/**
 * Explicitly invoked, transaction-bound ledger writer. No automatic rollout.
 * Rates and approvals are read from Office, never from HTTP payloads.
 */
final class OfficeApprovedLabourCostPostingService
{
    public function __construct(private PDO $db) {}

    public function post(int $approvedHoursId): int
    {
        if ($approvedHoursId < 1 || $this->db->inTransaction()) throw new \LogicException('Invalid posting transaction');
        $this->db->beginTransaction();
        try {
            $id=$this->postInsideTransaction($approvedHoursId);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Participate in an existing approval transaction; caller owns commit/rollback. */
    public function postInsideTransaction(int $approvedHoursId): int
    {
        if ($approvedHoursId < 1 || !$this->db->inTransaction()) throw new \LogicException('Approval transaction required');
        {
            $q=$this->db->prepare('SELECT id,project_id,person_id,work_date,approved_seconds,source_sha256
                FROM office_inzet_approved_hours WHERE id=? FOR UPDATE');
            $q->execute([$approvedHoursId]);
            $approved=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($approved)) throw new \DomainException('Approved hours missing');
            foreach (['id','project_id','person_id','approved_seconds'] as $field) {
                if (!ctype_digit((string)$approved[$field]) || (int)$approved[$field] < 1) {
                    throw new \DomainException('Invalid approved hours');
                }
                $approved[$field]=(int)$approved[$field];
            }
            $existing=$this->db->prepare('SELECT id,source_sha256 FROM office_inzet_actual_labour_costs WHERE approved_hours_id=? FOR UPDATE');
            $existing->execute([$approvedHoursId]);
            $row=$existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                if (!hash_equals((string)$row['source_sha256'],(string)$approved['source_sha256'])) {
                    throw new \DomainException('Previously posted approval evidence changed');
                }
                return (int)$row['id'];
            }
            $rates=$this->db->prepare('SELECT project_id,person_id,valid_from,valid_until,hourly_rate_cents,
                authorized_by,revoked_at FROM office_inzet_actual_labour_rates
                WHERE project_id=? AND person_id=? AND valid_from<=?
                AND (valid_until IS NULL OR valid_until>=?) FOR UPDATE');
            $rates->execute([$approved['project_id'],$approved['person_id'],$approved['work_date'],$approved['work_date']]);
            $records=[];
            foreach ($rates->fetchAll(PDO::FETCH_ASSOC) as $rate) {
                if ($rate['revoked_at'] !== null || trim((string)$rate['authorized_by'])==='') {
                    throw new \DomainException('Unauthorized effective labour rate');
                }
                $rate['project_id']=(int)$rate['project_id'];
                $rate['person_id']=(int)$rate['person_id'];
                $rate['hourly_rate_cents']=(int)$rate['hourly_rate_cents'];
                $rate['authorized']=true;
                $records[]=$rate;
            }
            $valuation=OfficeApprovedLabourValuation::prepare($approved,$records);
            $insert=$this->db->prepare('INSERT INTO office_inzet_actual_labour_costs
                (approved_hours_id,project_id,person_id,work_date,approved_seconds,hourly_rate_cents,amount_cents,source_sha256)
                VALUES (?,?,?,?,?,?,?,?)');
            $insert->execute([$valuation['approved_hours_id'],$valuation['project_id'],$valuation['person_id'],
                $valuation['work_date'],$valuation['approved_seconds'],$valuation['hourly_rate_cents'],
                $valuation['actual_cost_cents'],$valuation['source_sha256']]);
            $id=(int)$this->db->lastInsertId();
            return $id;
        }
    }
}
