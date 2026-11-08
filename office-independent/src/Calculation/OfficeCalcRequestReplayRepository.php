<?php
declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Atomic replay barrier for Calc signed Office API requests.
 * Caller must verify HMAC/timestamp before reserving request ID.
 * Failures are not swallowed: DB outage must deny the request.
 */
final class OfficeCalcRequestReplayRepository
{
    public function __construct(private readonly PDO $db) {}

    public function reserve(string $requestId, int $now): bool
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD', $requestId)
            || $now <= 0) {
            return false;
        }
        try {
            $statement = $this->db->prepare(
                'INSERT INTO office_calc_signed_request_ids (request_id, expires_at)
                 VALUES (:request_id, :expires_at)'
            );
            $statement->execute([
                ':request_id' => strtolower($requestId),
                ':expires_at' => gmdate('Y-m-d H:i:s', $now + 180),
            ]);
            return true;
        } catch (\PDOException $exception) {
            // Only a unique-key collision is a replay. Other failures deny
            // by propagating to the endpoint's fail-closed error handler.
            if ((string)$exception->getCode() === '23000') {
                return false;
            }
            throw $exception;
        }
    }
}
