<?php
declare(strict_types=1);
namespace Brebo\Office\Inspection;

/** Review-only schema. Apply only after Office-test migration preflight. */
final class OfficeInspectionSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_inspections (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_id BIGINT UNSIGNED NOT NULL,
        project_id BIGINT UNSIGNED NULL,
        title VARCHAR(200) NOT NULL,
        inspection_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_by BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inspections_building (building_id, inspection_date),
        CONSTRAINT fk_inspection_building FOREIGN KEY (building_id) REFERENCES office_buildings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_inspection_findings (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        inspection_id BIGINT UNSIGNED NOT NULL,
        code VARCHAR(30) NOT NULL,
        floor_label VARCHAR(120) NULL,
        room_label VARCHAR(120) NULL,
        building_part VARCHAR(160) NULL,
        observation TEXT NOT NULL,
        assessment_framework TEXT NULL,
        technical_assessment TEXT NULL,
        consequences TEXT NULL,
        remedy TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_inspection_finding_code (inspection_id, code),
        CONSTRAINT fk_finding_inspection FOREIGN KEY (inspection_id) REFERENCES office_inspections(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_inspection_evidence (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        finding_id BIGINT UNSIGNED NOT NULL,
        storage_key VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        caption VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inspection_evidence_finding (finding_id),
        CONSTRAINT fk_evidence_finding FOREIGN KEY (finding_id) REFERENCES office_inspection_findings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
