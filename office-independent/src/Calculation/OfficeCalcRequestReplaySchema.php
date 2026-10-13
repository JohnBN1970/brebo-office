<?php

declare(strict_types=1);

namespace Brebo\Office\Calculation;

/**
 * Opt-in schema; not applied by the application or by CI.
 * Retain IDs at least for the verifier's complete timestamp acceptance window.
 */
final class OfficeCalcRequestReplaySchema {
  public const MYSQL = <<<'SQL'
CREATE TABLE IF NOT EXISTS office_calc_request_nonces (
  request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  request_timestamp BIGINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX office_calc_request_nonces_created_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
}
