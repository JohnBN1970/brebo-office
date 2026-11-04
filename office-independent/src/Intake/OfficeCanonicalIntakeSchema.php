<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Additive Office-test canonical request foundation; no automatic migration.
 * Installation requires a separately gated operator action and schema audit.
 * Keep a request independent from any later project or opportunity.
 */
final class OfficeCanonicalIntakeSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_intake_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(32) NOT NULL,
                source_request_id VARCHAR(128) NOT NULL,
                relation_id BIGINT UNSIGNED NULL,
                building_id BIGINT UNSIGNED NULL,
                building_resolution VARCHAR(24) NOT NULL DEFAULT 'required',
                status VARCHAR(24) NOT NULL DEFAULT 'received',
                received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_intake_source_request (source, source_request_id),
                KEY idx_intake_relation (relation_id),
                KEY idx_intake_building (building_id),
                KEY idx_intake_status_received (status, received_at),
                CONSTRAINT fk_intake_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id),
                CONSTRAINT fk_intake_building FOREIGN KEY (building_id) REFERENCES office_buildings(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_intake_request_disciplines (
                request_id BIGINT UNSIGNED NOT NULL,
                discipline_code VARCHAR(32) NOT NULL,
                blueprint_key VARCHAR(96) NOT NULL,
                blueprint_version INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (request_id, discipline_code),
                CONSTRAINT fk_intake_discipline_request FOREIGN KEY (request_id)
                    REFERENCES office_intake_requests(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_intake_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                request_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_intake_event_request (request_id, id),
                CONSTRAINT fk_intake_event_request FOREIGN KEY (request_id)
                    REFERENCES office_intake_requests(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}
