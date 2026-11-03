<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

/** Review-only DDL, must be migrated explicitly in isolated Office-test. */
final class OfficeInspectionReportConfigurationSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_inspection_report_configs (
        inspection_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        report_type VARCHAR(32) NOT NULL DEFAULT 'expertise',
        sections_json JSON NOT NULL,
        notes TEXT NULL,
        updated_by BIGINT UNSIGNED NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_inspection_report_config_inspection FOREIGN KEY (inspection_id) REFERENCES office_inspections(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
