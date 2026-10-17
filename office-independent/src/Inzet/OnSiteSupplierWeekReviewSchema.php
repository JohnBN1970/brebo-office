<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** DDL proposal only. No automatic installation or production migration. */
final class OnSiteSupplierWeekReviewSchema {
  /** @return list<string> */
  public static function statements(): array {
    return [
      "CREATE TABLE IF NOT EXISTS office_inzet_project_suppliers (
        project_id BIGINT UNSIGNED NOT NULL,
        supplier_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (project_id, supplier_id),
        INDEX idx_onsite_project_suppliers_supplier (supplier_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_inzet_supplier_week_imports (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        supplier_id BIGINT UNSIGNED NOT NULL,
        iso_year SMALLINT UNSIGNED NOT NULL,
        iso_week TINYINT UNSIGNED NOT NULL,
        source_sha256 CHAR(64) NOT NULL,
        source_filename VARCHAR(255) NOT NULL,
        uploaded_by BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_supplier_week_source (supplier_id, iso_year, iso_week, source_sha256),
        INDEX idx_supplier_week (supplier_id, iso_year, iso_week)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_inzet_supplier_week_import_projects (
        import_id BIGINT UNSIGNED NOT NULL,
        project_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (import_id, project_id),
        INDEX idx_onsite_import_project (project_id),
        CONSTRAINT fk_onsite_import_project_import FOREIGN KEY (import_id)
          REFERENCES office_inzet_supplier_week_imports(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_inzet_supplier_week_decisions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        import_id BIGINT UNSIGNED NOT NULL,
        reviewer_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(32) NOT NULL,
        reason TEXT NOT NULL,
        source_sha256 CHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_onsite_week_review_import FOREIGN KEY (import_id)
          REFERENCES office_inzet_supplier_week_imports(id),
        INDEX idx_onsite_week_decision_history (import_id, created_at, id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
  }
}
