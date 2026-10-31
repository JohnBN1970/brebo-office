<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

/**
 * Native Office communication storage.
 *
 * Schema installation is explicit; never run from an HTTP request.
 * No mail transport or provider credentials are stored here.
 */
final class OfficeCommunicationSchema
{
    /** @return list<string> */
    public static function statements(): array
    {
        return [
            "CREATE TABLE office_communication_mailboxes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                mailbox_key VARCHAR(191) NOT NULL,
                provider VARCHAR(32) NOT NULL DEFAULT 'zoho',
                display_name VARCHAR(255) NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_communication_mailbox_key (mailbox_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE office_communication_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                mailbox_id BIGINT UNSIGNED NOT NULL,
                folder VARCHAR(191) NOT NULL,
                uidvalidity BIGINT UNSIGNED NOT NULL,
                imap_uid BIGINT UNSIGNED NOT NULL,
                source_message_id VARCHAR(255) NULL,
                sender_address VARCHAR(320) NULL,
                subject TEXT NULL,
                body_text MEDIUMTEXT NULL,
                received_at DATETIME NULL,
                review_status VARCHAR(32) NOT NULL DEFAULT 'pending',
                processing_status VARCHAR(32) NOT NULL DEFAULT 'stored',
                processing_attempts INT UNSIGNED NOT NULL DEFAULT 0,
                last_error_code VARCHAR(100) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_communication_source (mailbox_id, folder, uidvalidity, imap_uid),
                KEY idx_communication_review (review_status, created_at),
                KEY idx_communication_processing (processing_status, updated_at),
                CONSTRAINT fk_communication_message_mailbox FOREIGN KEY (mailbox_id)
                    REFERENCES office_communication_mailboxes(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE office_communication_review_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                message_id BIGINT UNSIGNED NOT NULL,
                operator_id VARCHAR(128) NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                event_data JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_communication_event_message (message_id, created_at),
                CONSTRAINT fk_communication_event_message FOREIGN KEY (message_id)
                    REFERENCES office_communication_messages(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}
