<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use PDO;
use Throwable;

/**
 * Calc-side redemption guard. Never grants access based on a grant alone:
 * authenticated session, current CRM opportunity policy and one-time
 * redemption are all required. HTTP integration must be POST/HTTPS only.
 */
final class OfficeCalcCrmLaunchRedeemer
{
    public function __construct(private readonly PDO $db) {}

    public function redeem(string $reference,string $sessionHash,int $actorId,
        int $draftId,int $now,callable $canManageOpportunity): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$reference)
            || !preg_match('/^[a-f0-9]{64}$/D',$sessionHash)
            || $actorId < 1 || $draftId < 1 || $now < 1
            || $this->db->inTransaction()
            || $this->db->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION) return false;
        try {
            $this->db->beginTransaction();
            $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->db->prepare(
                'SELECT g.reference FROM office_calc_crm_launch_grants g '.
                'INNER JOIN office_operator_sessions s ON s.token_hash=g.session_hash '.
                'INNER JOIN office_operator_accounts a ON a.id=g.actor_id '.
                'WHERE g.reference=? AND g.session_hash=? AND g.actor_id=? AND g.draft_id=? '.
                'AND g.consumed_at IS NULL AND g.revoked_at IS NULL AND g.expires_at>? '.
                'AND s.revoked_at IS NULL AND s.expires_at>? AND a.active=1'.$lock
            );
            $at=gmdate('Y-m-d H:i:s',$now);
            $q->execute([$reference,$sessionHash,$actorId,$draftId,$at,$at]);
            if ($q->fetchColumn()===false) { $this->db->rollBack();return false; }
            require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';
            if (!(new OfficeCalcCrmDraftAccess($this->db))->canManage($actorId,$draftId,$canManageOpportunity)
                || !$this->db->inTransaction()) {
                if($this->db->inTransaction())$this->db->rollBack();
                return false;
            }
            require_once __DIR__.'/OfficeCalcCrmLaunchGrantRepository.php';
            if (!(new OfficeCalcCrmLaunchGrantRepository($this->db))
                ->consume($reference,$sessionHash,$actorId,$draftId,$now)) {
                $this->db->rollBack();return false;
            }
            $this->db->commit();
            return true;
        } catch (Throwable) {
            if($this->db->inTransaction())$this->db->rollBack();
            return false;
        }
    }
}
