<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

/** Native Office-test mail review queue. No Drupal dependency or automatic CRM writes. */
final class OfficeMailInboxSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_mail_review_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(32) NOT NULL,
                source_message_id VARCHAR(255) NOT NULL,
                sender_address VARCHAR(320) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                received_at DATETIME NOT NULL,
                intent VARCHAR(64) NOT NULL DEFAULT 'other',
                confidence DECIMAL(5,4) NOT NULL DEFAULT 0,
                suggested_action VARCHAR(64) NOT NULL DEFAULT 'manual_review',
                review_status VARCHAR(24) NOT NULL DEFAULT 'pending',
                relation_id BIGINT UNSIGNED NULL,
                opportunity_id BIGINT UNSIGNED NULL,
                project_id BIGINT UNSIGNED NULL,
                building_id BIGINT UNSIGNED NULL,
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_office_mail_source_message (source, source_message_id),
                KEY idx_office_mail_review (review_status, received_at),
                KEY idx_office_mail_relation (relation_id),
                KEY idx_office_mail_project (project_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];
    }
}
