<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use PDO;
use Throwable;

/**
 * POST handler service for CRM-first Calc. HTTP adapter must enforce HTTPS,
 * POST and same-origin protections. Returns an opaque single-use reference,
 * never a URL. The receiving Calc side must call OfficeCalcCrmLaunchRedeemer.
 */
final class OfficeCalcCrmLaunchIssuer
{
    public function __construct(private readonly PDO $db) {}

    public function issue(string $sessionToken,string $csrfSubmitted,
        string $csrfExpected,int $draftId,int $now,
        callable $canManageOpportunity): ?string
    {
        try {
            require_once __DIR__.'/OfficeCalcCrmLaunchGate.php';
            require_once __DIR__.'/OfficeCalcCrmLaunchGrantRepository.php';
            $identity=(new OfficeCalcCrmLaunchGate($this->db))->authorize(
                $sessionToken,$csrfSubmitted,$csrfExpected,$draftId,$now,$canManageOpportunity
            );
            if ($identity===null) return null;
            return (new OfficeCalcCrmLaunchGrantRepository($this->db))->issue(
                $identity['session_id'],$identity['actor_id'],$identity['draft_id'],$now
            );
        } catch (Throwable) {
            return null;
        }
    }
    /**
     * Optional migration contract: return a v2 HMAC assertion plus the
     * opaque Office reference. Never expose this unless the Office->Calc
     * continuous grant verifier has been installed and verified.
     */
    public function issueSigned(
        string $sessionToken,
        string $csrfSubmitted,
        string $csrfExpected,
        int $draftId,
        int $now,
        string $sharedSecret,
        callable $canManageOpportunity
    ): ?array {
        if (strlen($sharedSecret) < 32) return null;
        try {
            require_once __DIR__.'/OfficeCalcCrmLaunchGate.php';
            require_once __DIR__.'/OfficeCalcCrmLaunchGrantRepository.php';
            require_once __DIR__.'/OfficeCalcCrmSignedLaunch.php';
            $identity=(new OfficeCalcCrmLaunchGate($this->db))->authorize(
                $sessionToken,$csrfSubmitted,$csrfExpected,$draftId,$now,$canManageOpportunity
            );
            if ($identity===null) return null;
            $reference=(new OfficeCalcCrmLaunchGrantRepository($this->db))->issue(
                $identity['session_id'],$identity['actor_id'],$identity['draft_id'],$now,28800
            );
            $token=OfficeCalcCrmSignedLaunch::issue(
                $identity['draft_id'],$identity['actor_id'],$reference,$sharedSecret,$now
            );
            return ['reference'=>$reference,'token'=>$token,'draft_id'=>$draftId];
        } catch (Throwable) {
            return null;
        }
    }

}
