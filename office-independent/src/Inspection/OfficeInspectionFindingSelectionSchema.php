<?php
declare(strict_types=1);

namespace Brebo\Office\Inspection;

/** Review-only DDL. Keep report inclusion separate from canonical observations. */
final class OfficeInspectionFindingSelectionSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_inspection_report_findings (
        inspection_id BIGINT UNSIGNED NOT NULL,
        finding_id BIGINT UNSIGNED NOT NULL,
        include_in_report TINYINT(1) NOT NULL DEFAULT 1,
        updated_by BIGINT UNSIGNED NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (inspection_id,finding_id),
        CONSTRAINT fk_report_finding_inspection FOREIGN KEY (inspection_id) REFERENCES office_inspections(id),
        CONSTRAINT fk_report_finding_finding FOREIGN KEY (finding_id) REFERENCES office_inspection_findings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
