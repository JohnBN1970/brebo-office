<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;
use Throwable;

/**
 * Issues a scoped, one-time Calc launch credential only after verified
 * Office session and current project/calculation authorization.
 *
 * The browser/HTTP adapter MUST validate CSRF and use a POST-only handoff;
 * do not place the returned token in URLs or logs.
 */
final class OfficeCalcLaunchIssuer
{
    public function __construct(private readonly PDO $db) {}

    public function issue(
        string $sessionToken,
        int $projectId,
        int $calculationId,
        string $sharedSecret,
        int $now
    ): ?string {
        if ($projectId <= 0 || $calculationId <= 0 || strlen($sharedSecret) < 32 || $now <= 0) {
            return null;
        }
        try {
            require_once dirname(__DIR__) . '/Intake/WebsiteCrmOperatorContext.php';
            require_once dirname(__DIR__) . '/Intake/WebsiteOfficeCrmSessionRepository.php';
            require_once __DIR__ . '/OfficeCalcLaunchAccess.php';
            require_once __DIR__ . '/OfficeCalcSessionGrantRepository.php';
            require_once __DIR__ . '/OfficeCalcLaunchToken.php';
            $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($this->db))
                ->findActive($sessionToken, $now);
            if ($operator === null || !ctype_digit($operator->operatorId)) {
                return null;
            }
            $actorId = (int)$operator->operatorId;
            if ($actorId <= 0 || !(new OfficeCalcLaunchAccess($this->db))
                ->canLaunch($actorId, $projectId, $calculationId)) {
                return null;
            }
            // The grant is durable before a token can be handed to the browser.
            $reference = (new OfficeCalcSessionGrantRepository($this->db))->issue(
                $operator->sessionId, $actorId, $projectId, $calculationId, $now
            );
            return OfficeCalcLaunchToken::issue(
                $calculationId, $projectId, $actorId, $sharedSecret, $now, $reference
            );
        } catch (Throwable) {
            return null;
        }
    }
}
