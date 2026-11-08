<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/** Additive schema; never changes or deletes existing relations. */
final class OfficeCrmOpportunitySchema {
    public static function statements(): array {
        return [
            "CREATE TABLE IF NOT EXISTS office_crm_opportunities (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                relation_id BIGINT UNSIGNED NOT NULL,
                contact_id BIGINT UNSIGNED NULL,
                title VARCHAR(255) NOT NULL,
                stage VARCHAR(32) NOT NULL DEFAULT 'prospect',
                description TEXT NULL,
                expected_value DECIMAL(14,2) NULL,
                expected_close_date DATE NULL,
                next_action VARCHAR(255) NULL,
                next_action_at DATETIME NULL,
                opname_required TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(16) NOT NULL DEFAULT 'open',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_crm_opp_relation (relation_id),
                KEY idx_crm_opp_stage (stage, status),
                KEY idx_crm_opp_calendar (next_action_at),
                CONSTRAINT fk_crm_opp_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id),
                CONSTRAINT fk_crm_opp_contact FOREIGN KEY (contact_id) REFERENCES office_crm_contact_people(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_opportunity_activities (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                opportunity_id BIGINT UNSIGNED NOT NULL,
                activity_type VARCHAR(32) NOT NULL,
                title VARCHAR(255) NOT NULL,
                scheduled_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_crm_opp_activity_calendar (scheduled_at),
                CONSTRAINT fk_crm_opp_activity FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}
