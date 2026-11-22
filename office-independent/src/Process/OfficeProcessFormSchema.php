<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

/** Additive, opt-in storage. No runtime migration or production writes. */
final class OfficeProcessFormSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_process_form_instances (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                document_code VARCHAR(16) NOT NULL,
                document_version VARCHAR(32) NOT NULL,
                context_type VARCHAR(32) NOT NULL,
                context_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'concept',
                revision INT UNSIGNED NOT NULL DEFAULT 1,
                data_json LONGTEXT NOT NULL,
                created_by VARCHAR(128) NOT NULL,
                updated_by VARCHAR(128) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_process_forms_context (context_type,context_id),
                INDEX idx_process_forms_document (document_code,status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_process_form_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                form_id BIGINT UNSIGNED NOT NULL,
                revision INT UNSIGNED NOT NULL,
                actor_operator_id VARCHAR(128) NOT NULL,
                event_type VARCHAR(32) NOT NULL,
                reason VARCHAR(500) NOT NULL,
                snapshot_json LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_process_form_event_revision (form_id,revision),
                CONSTRAINT fk_process_form_event_instance FOREIGN KEY (form_id) REFERENCES office_process_form_instances(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_process_form_evidence (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                form_id BIGINT UNSIGNED NOT NULL,
                document_id BIGINT UNSIGNED NULL,
                evidence_ref VARCHAR(255) NOT NULL,
                linked_by VARCHAR(128) NOT NULL,
                linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_process_form_evidence_form (form_id),
                CONSTRAINT fk_process_form_evidence_instance FOREIGN KEY (form_id) REFERENCES office_process_form_instances(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}
