<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Review-only DDL for the isolated Office test database; never executed automatically. */
final class OnSiteClockEventSchema {
  public static function statement(): string {
    return "CREATE TABLE IF NOT EXISTS office_inzet_clock_events (
      event_id VARCHAR(128) NOT NULL PRIMARY KEY,
      project_id BIGINT UNSIGNED NOT NULL,
      person_id BIGINT UNSIGNED NOT NULL,
      action VARCHAR(8) NOT NULL,
      occurred_at VARCHAR(40) NOT NULL,
      latitude DECIMAL(10,7) NULL,
      longitude DECIMAL(10,7) NULL,
      accuracy_m DECIMAL(12,4) NULL,
      received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_onsite_project_time (project_id, received_at),
      INDEX idx_onsite_person_time (person_id, received_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  }
}
