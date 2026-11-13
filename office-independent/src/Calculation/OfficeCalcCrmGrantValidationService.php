<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;
use Throwable;

/**
 * Signed Calc -> Office continuous grant check for CRM drafts without a project.
 * A v2 HMAC credential alone never authorizes use: active Office operator
 * session, draft ownership, CRM revision/status, and independent grant lifetime
 * are checked on each protected Calc API call.
 */
final class OfficeCalcCrmGrantValidationService
{
    public function __construct(private readonly PDO $db) {}

    public function validate(string $rawBody, array $headers, string $secret, int $now): bool
    {
        try {
            require_once __DIR__.'/OfficeCalcSignedRequest.php';
            require_once __DIR__.'/OfficeCalcRequestReplayRepository.php';
            $timestamp=$headers['x-brebo-timestamp']??null;
            $requestId=$headers['x-brebo-request-id']??null;
            $signature=$headers['x-brebo-signature']??null;
            if (!is_string($timestamp)||!is_string($requestId)||!is_string($signature)
                || !OfficeCalcSignedRequest::verify(
                    'POST','/api/workbench/v1/calculation-grants/validate',
                    $rawBody,$timestamp,$requestId,$signature,$secret,$now
                )) return false;
            if (!(new OfficeCalcRequestReplayRepository($this->db))->reserve($requestId,$now)) return false;
            $data=json_decode($rawBody,true,8,JSON_THROW_ON_ERROR);
            if (!is_array($data)||array_is_list($data)
                || !isset($data['reference'],$data['actor_id'],$data['calculation_id'])
                || !array_key_exists('project_id',$data) || $data['project_id']!==null
                || !is_string($data['reference'])
                || preg_match('/^[a-f0-9]{64}$/D',$data['reference'])!==1
                || !is_string($data['actor_id'])
                || preg_match('/^[1-9][0-9]{0,15}$/D',$data['actor_id'])!==1
                || !is_int($data['calculation_id'])||$data['calculation_id']<1) return false;
            $actor=(int)$data['actor_id'];
            $draft=$data['calculation_id'];
            $q=$this->db->prepare(
                'SELECT g.reference FROM office_calc_crm_launch_grants g '.
                'INNER JOIN office_operator_sessions s ON s.token_hash=g.session_hash '.
                'INNER JOIN office_operator_accounts a ON a.id=g.actor_id '.
                'INNER JOIN office_calc_crm_drafts d ON d.id=g.draft_id '.
                'INNER JOIN office_crm_opportunities o ON o.id=d.opportunity_id '.
                'WHERE g.reference=? AND g.actor_id=? AND g.draft_id=? '.
                'AND g.consumed_at IS NULL AND g.revoked_at IS NULL AND g.expires_at>? '.
                'AND s.operator_id=? AND s.revoked_at IS NULL AND s.expires_at>? '.
                'AND a.active=1 AND a.can_edit_crm=1 '.
                'AND d.created_by=? AND d.project_id IS NULL '.
                'AND d.crm_revision=o.revision AND o.status=\'open\' '.
                'AND o.stage IN (\'prospect\',\'opportunity\',\'proposal\',\'negotiation\') '.
                'LIMIT 1'
            );
            $utc=gmdate('Y-m-d H:i:s',$now);
            $q->execute([$data['reference'],$actor,$draft,$utc,$data['actor_id'],$utc,$actor]);
            return $q->fetchColumn()!==false;
        } catch (Throwable) {
            return false;
        }
    }
}
