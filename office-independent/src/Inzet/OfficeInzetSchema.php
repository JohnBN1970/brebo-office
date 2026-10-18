<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Proposed isolated Office Inzet schema. Never runs DDL automatically.
 * Apply only after explicit test-database review and migration approval.
 * Project foreign keys deliberately await verification of office_projects.id type.
 */
final class OfficeInzetSchema {
  /** @return list<string> */
  public static function statements(): array {
    return [
      "CREATE TABLE IF NOT EXISTS office_inzet_people (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        personnel_number VARCHAR(64) NULL,
        display_name VARCHAR(200) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uniq_inzet_personnel_number (personnel_number)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_inzet_project_team (
        project_id BIGINT UNSIGNED NOT NULL,
        person_id BIGINT UNSIGNED NOT NULL,
        role_label VARCHAR(120) NULL,
        PRIMARY KEY (project_id, person_id),
        CONSTRAINT fk_inzet_team_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_inzet_availability (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        person_id BIGINT UNSIGNED NOT NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        availability_kind VARCHAR(32) NOT NULL,
        CONSTRAINT fk_inzet_availability_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
        INDEX idx_inzet_availability_person_dates (person_id, starts_at, ends_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_inzet_shifts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        project_id BIGINT UNSIGNED NOT NULL,
        person_id BIGINT UNSIGNED NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'planned',
        CONSTRAINT fk_inzet_shift_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
        INDEX idx_inzet_shift_project_dates (project_id, starts_at, ends_at),
        INDEX idx_inzet_shift_person_dates (person_id, starts_at, ends_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
  }
}
