<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

use PDO;

/**
 * Atomic one-time acceptance of a signed Calc request ID.
 * Requires the dedicated office_calc_request_nonces table to be provisioned.
 * Database failure is intentionally propagated to the endpoint (fail closed).
 */
final class OfficeCalcRequestReplayGuard {
  public function __construct(private readonly PDO $db) {}

  public function accept(string $requestId, int $timestamp, ?int $now = null): bool {
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $requestId)
      || abs(($now ?? time()) - $timestamp) > 120) {
      return false;
    }
    try {
      $stmt = $this->db->prepare(
        'INSERT INTO office_calc_request_nonces (request_id, request_timestamp) VALUES (:id, :ts)'
      );
      $stmt->execute([':id' => strtolower($requestId), ':ts' => $timestamp]);
      return true;
    } catch (\PDOException $e) {
      // Duplicate key means replay. Other database errors must not be hidden.
      if ((string) $e->getCode() === '23000') {
        return false;
      }
      throw $e;
    }
  }
}
