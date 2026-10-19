<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Review-only MySQL inbox migration. No runtime DDL and no deployment hook.
 * A unique event id makes device retries idempotent.
 */
final class OnSiteEventInboxSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_onsite_event_inbox (
        event_id VARCHAR(100) NOT NULL PRIMARY KEY,
        person_id BIGINT UNSIGNED NOT NULL,
        project_id VARCHAR(64) NOT NULL,
        zone_id VARCHAR(64) NOT NULL,
        event_kind VARCHAR(3) NOT NULL,
        occurred_at_utc VARCHAR(27) NOT NULL,
        received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_onsite_person_project_time (person_id, project_id, occurred_at_utc),
        CONSTRAINT fk_onsite_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
