<?php
declare(strict_types=1);
namespace Brebo\Office\Demo;

/** Additive, explicitly installed CRM pricing policy tables. No automatic DDL. */
final class OfficeCommercialProfileSchema {
    public static function statements(): array {
        return [
            "CREATE TABLE IF NOT EXISTS office_commercial_profiles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                position_code VARCHAR(32) NOT NULL,
                version_code VARCHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_commercial_profile_version (position_code, version_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_commercial_profile_rules (
                profile_id BIGINT UNSIGNED NOT NULL,
                category_code VARCHAR(32) NOT NULL,
                material_markup_pct DECIMAL(9,4) NOT NULL,
                labor_markup_pct DECIMAL(9,4) NOT NULL,
                PRIMARY KEY (profile_id, category_code),
                CONSTRAINT fk_commercial_rule_profile FOREIGN KEY (profile_id) REFERENCES office_commercial_profiles(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_commercial_context (
                source_type VARCHAR(32) NOT NULL,
                source_id VARCHAR(128) NOT NULL,
                position_code VARCHAR(32) NOT NULL,
                work_categories_json JSON NOT NULL,
                work_scope VARCHAR(64) NULL,
                profile_id BIGINT UNSIGNED NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (source_type, source_id),
                CONSTRAINT fk_crm_commercial_profile FOREIGN KEY (profile_id) REFERENCES office_commercial_profiles(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
