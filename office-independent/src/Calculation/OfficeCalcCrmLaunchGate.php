<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use PDO;
use Throwable;

/**
 * CRM-first launch authorization only. Does not issue a Calc credential.
 * HTTP caller must enforce POST, CSRF, HTTPS and avoid logging credentials.
 */
final class OfficeCalcCrmLaunchGate
{
    public function __construct(private readonly PDO $db) {}

    public function authorize(string $sessionToken, string $csrfSubmitted,
        string $csrfExpected, int $draftId, int $now,
        callable $canManageOpportunity): ?array
    {
        if ($sessionToken === '' || $draftId < 1 || $now < 1
            || strlen($csrfExpected) < 32 || $csrfSubmitted === ''
            || !hash_equals($csrfExpected,$csrfSubmitted)) return null;
        try {
            require_once dirname(__DIR__).'/Intake/WebsiteOfficeCrmSessionRepository.php';
            require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';
            $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($this->db))
                ->findActive($sessionToken,$now);
            if ($operator === null || !ctype_digit($operator->operatorId)) return null;
            $actorId = (int)$operator->operatorId;
            if ($actorId < 1 || !(new OfficeCalcCrmDraftAccess($this->db))
                ->canManage($actorId,$draftId,$canManageOpportunity)) return null;
            return ['draft_id'=>$draftId,'actor_id'=>$actorId,'session_id'=>$operator->sessionId];
        } catch (Throwable) {
            return null;
        }
    }
}
