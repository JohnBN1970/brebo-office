<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Additive, opt-in personnel details schema. Never executes DDL automatically.
 * The existing office_inzet_people row remains the authoritative identity.
 */
final class OfficePersonnelDetailsSchema {
  /** @return list<string> */
  public static function statements(): array {
    return [
      "CREATE TABLE IF NOT EXISTS office_inzet_personnel_details (
        person_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        phone_country CHAR(2) NULL,
        phone_e164 VARCHAR(20) NULL,
        nationality CHAR(2) NULL,
        preferred_language VARCHAR(8) NULL,
        vca_expiry DATE NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_inzet_personnel_details_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
        UNIQUE KEY uniq_inzet_personnel_phone (phone_e164)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_inzet_personnel_skills (
        person_id BIGINT UNSIGNED NOT NULL,
        skill_code VARCHAR(80) NOT NULL,
        PRIMARY KEY (person_id, skill_code),
        CONSTRAINT fk_inzet_personnel_skill_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
  }
}
