<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use PDO;
use DomainException;

/**
 * One-time CRM draft grant store. Caller must first validate Office session,
 * CSRF and CRM opportunity rights. Never use project-bound Calc grant table.
 */
final class OfficeCalcCrmLaunchGrantRepository
{
    public function __construct(private readonly PDO $db) {}

    public function issue(string $sessionHash,int $actorId,int $draftId,int $now,int $ttlSeconds=90): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$sessionHash)
            || $actorId < 1 || $draftId < 1 || $now < 1
            || $ttlSeconds < 1 || $ttlSeconds > 28800) {
            throw new DomainException('Invalid CRM Calc grant identity');
        }
        $reference=bin2hex(random_bytes(32));
        $q=$this->db->prepare('INSERT INTO office_calc_crm_launch_grants(reference,session_hash,actor_id,draft_id,expires_at) VALUES (?,?,?,?,?)');
        $q->execute([$reference,$sessionHash,$actorId,$draftId,gmdate('Y-m-d H:i:s',$now+$ttlSeconds)]);
        return $reference;
    }

    /**
     * Atomic single-use redemption. The Calc-side caller must independently
     * recheck current Office session, CRM draft and operator authorization.
     */
    public function consume(string $reference,string $sessionHash,int $actorId,int $draftId,int $now): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$reference)
            || !preg_match('/^[a-f0-9]{64}$/D',$sessionHash)
            || $actorId < 1 || $draftId < 1 || $now < 1) return false;
        $q=$this->db->prepare(
            'UPDATE office_calc_crm_launch_grants SET consumed_at=? '.
            'WHERE reference=? AND session_hash=? AND actor_id=? AND draft_id=? '.
            'AND consumed_at IS NULL AND revoked_at IS NULL AND expires_at>?'
        );
        $nowUtc=gmdate('Y-m-d H:i:s',$now);
        $q->execute([$nowUtc,$reference,$sessionHash,$actorId,$draftId,$nowUtc]);
        return $q->rowCount()===1;
    }
}
