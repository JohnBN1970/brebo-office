<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;
use Throwable;

/**
 * Calc -> Office grant validation service. No HTTP output or route registration.
 * The endpoint adapter must enforce HTTPS, host, method, path, JSON size and
 * appropriate no-store headers. No grant is valid without current ACL checks.
 */
final class OfficeCalcGrantValidationService
{
    public function __construct(private readonly PDO $db) {}

    public function validate(
        string $rawBody,
        array $headers,
        string $sharedSecret,
        int $now
    ): bool {
        try {
            require_once __DIR__ . '/OfficeCalcSignedRequest.php';
            require_once __DIR__ . '/OfficeCalcRequestReplayRepository.php';
            require_once __DIR__ . '/OfficeCalcSessionGrantRepository.php';
            require_once __DIR__ . '/OfficeCalcLaunchAccess.php';

            $timestamp = $headers['x-brebo-timestamp'] ?? null;
            $requestId = $headers['x-brebo-request-id'] ?? null;
            $signature = $headers['x-brebo-signature'] ?? null;
            if (!is_string($timestamp) || !is_string($requestId) || !is_string($signature)
                || !OfficeCalcSignedRequest::verify(
                    'POST', '/api/workbench/v1/calculation-grants/validate',
                    $rawBody, $timestamp, $requestId, $signature, $sharedSecret, $now
                )) {
                return false;
            }
            // Reserve before checking the grant, including denied requests:
            // an authenticated request ID must never be reusable.
            if (!(new OfficeCalcRequestReplayRepository($this->db))->reserve($requestId, $now)) {
                return false;
            }
            $data = json_decode($rawBody, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_is_list($data)
                || !isset($data['reference'], $data['actor_id'], $data['project_id'], $data['calculation_id'])
                || !is_string($data['reference'])
                || !is_int($data['actor_id']) || !is_int($data['project_id']) || !is_int($data['calculation_id'])) {
                return false;
            }
            $actorId = $data['actor_id'];
            $projectId = $data['project_id'];
            $calculationId = $data['calculation_id'];
            if (!(new OfficeCalcSessionGrantRepository($this->db))->matchesActiveSession(
                $data['reference'], $actorId, $projectId, $calculationId, $now
            )) {
                return false;
            }
            return (new OfficeCalcLaunchAccess($this->db))->canLaunch($actorId, $projectId, $calculationId);
        } catch (Throwable) {
            // Never expose DB, authorization or signing errors to Calc.
            return false;
        }
    }
}
