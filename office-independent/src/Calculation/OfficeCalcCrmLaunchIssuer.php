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
}
