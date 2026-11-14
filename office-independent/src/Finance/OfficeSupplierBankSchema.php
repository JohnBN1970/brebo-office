<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Additive Office-test schema proposal. statements() returns SQL; it NEVER executes it.
 * Installation requires an explicitly authorized Office-test deployment step.
 */
final class OfficeSupplierBankSchema {
    public static function statements(): array {
        return [
            "CREATE TABLE IF NOT EXISTS office_crm_relation_bank_accounts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                relation_id BIGINT UNSIGNED NOT NULL,
                account_type VARCHAR(16) NOT NULL DEFAULT 'regular',
                iban VARCHAR(34) NOT NULL,
                account_holder VARCHAR(255) NOT NULL,
                bic VARCHAR(11) NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'inactive',
                verification_status VARCHAR(16) NOT NULL DEFAULT 'pending',
                verification_evidence_id VARCHAR(128) NULL,
                verified_by BIGINT UNSIGNED NULL,
                verified_at DATETIME NULL,
                verified_fingerprint CHAR(64) NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_office_relation_bank_account (relation_id, account_type, iban),
                KEY idx_office_bank_relation_status (relation_id, status, verification_status),
                CONSTRAINT fk_office_bank_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_bank_account_audit (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                bank_account_id BIGINT UNSIGNED NOT NULL,
                relation_id BIGINT UNSIGNED NOT NULL,
                action_code VARCHAR(48) NOT NULL,
                actor_user_id BIGINT UNSIGNED NOT NULL,
                occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                old_values_json JSON NULL,
                new_values_json JSON NULL,
                evidence_reference VARCHAR(128) NULL,
                KEY idx_office_bank_audit_account_time (bank_account_id, occurred_at),
                CONSTRAINT fk_office_bank_audit_account FOREIGN KEY (bank_account_id) REFERENCES office_crm_relation_bank_accounts(id),
                CONSTRAINT fk_office_bank_audit_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_finance_payment_control_settings (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                four_eyes_required TINYINT(1) NOT NULL DEFAULT 1,
                changed_by BIGINT UNSIGNED NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT chk_office_finance_settings_singleton CHECK (id = 1)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}
